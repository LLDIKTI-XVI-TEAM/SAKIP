<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatatRealisasiKegiatan
{
    public function __construct(
        private readonly ResolveLockedActor $pengunci,
        private readonly KegiatanPolicy $policy,
        private readonly AuditLogger $audit,
        private readonly PersyaratanPerencanaanKegiatan $persyaratan,
    ) {}

    /** Audit penolakan; `$kurang` berisi persyaratan bukti yang belum terpenuhi bila gerbang terlaksana menolak. */
    public function catatPenolakan(User $actor, Kegiatan $kegiatan, string $alasan, ?PermissionDecision $decision = null, array $kurang = []): void
    {
        $decision ??= $this->policy->keputusanSimpan($actor, $kegiatan, (string) $kegiatan->unit_id);
        $this->audit->catat(
            actor: $actor, tindakan: $kurang === [] ? 'kegiatan.catat_realisasi_ditolak' : 'kegiatan.terlaksana_ditolak', objekTipe: 'kegiatan',
            objekId: (string) $kegiatan->id, nilaiBaru: $kurang === [] ? null : ['persyaratan_kurang' => $kurang], alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
        );
    }

    /**
     * Satu use case aksi pelaksanaan (Q69 §69.3 butir 4, Q74 §74.2): data realisasi boleh disimpan bertahap selama
     * `rencana`/`tidak_terlaksana` dan diperbarui setelah `terlaksana` tanpa mengubah status (Q45 §45.1 butir 4).
     * Penandaan `terlaksana` mewajibkan tanggal, biaya terpakai, dan peserta riil, lalu gerbang bukti tahap
     * pelaksanaan (PRD §15.6, Q22). Urutan lock sama dengan `UbahStatusKegiatan` dan gerbang berkas/klaim,
     * sehingga bukti dan klaim tidak berubah di antara penilaian gerbang dan penyimpanan.
     *
     * @param  array<string, mixed>  $data  Hasil validasi `CatatRealisasiKegiatanRequest`.
     * @return array{terlaksana: bool}
     */
    public function handle(User $actor, Kegiatan $kegiatan, array $data): array
    {
        $decision = null;
        $kurang = [];
        try {
            return DB::transaction(function () use ($actor, $kegiatan, $data, &$decision, &$kurang): array {
                $locked = $this->pengunci->handle($actor, 'kegiatan:update');
                $aktor = $locked['aktor'];
                if (! $aktor instanceof User || $aktor->status !== 'aktif') {
                    $decision = $locked['keputusan'];
                    throw new AuthorizationException('Akun tidak aktif.');
                }
                Periode::lockConfiguration();
                $headerIds = GerbangBerkasKegiatan::headerPengklaim((string) $kegiatan->id);
                RencanaAksi::whereIn('id', $headerIds)->orderBy('id')->lockForUpdate()->get();
                // Tahun dari binding hanya kandidat; dicek ulang setelah kegiatan terkunci.
                $ditutup = GerbangBerkasKegiatan::tahunDitutup((int) $kegiatan->tahun);
                $terkunci = Kegiatan::whereKey($kegiatan->id)->lockForUpdate()->firstOrFail();
                if ((int) $terkunci->tahun !== (int) $kegiatan->tahun || GerbangBerkasKegiatan::headerPengklaim((string) $kegiatan->id) !== $headerIds) {
                    throw $this->basi();
                }
                $decision = $this->policy->keputusanSimpan($aktor, $terkunci, (string) $terkunci->unit_id);
                if (($decision->basis['alasan'] ?? null) === 'inactive_unit') {
                    throw ValidationException::withMessages(['status' => 'Unit kegiatan harus tetap aktif.']);
                }
                if (! $decision->allowed) {
                    throw new AuthorizationException('Izin kegiatan tidak tersedia atau telah dicabut.');
                }
                if (Unit::whereKey($terkunci->unit_id)->sharedLock()->value('status') !== 'aktif') {
                    throw ValidationException::withMessages(['status' => 'Unit kegiatan harus tetap aktif.']);
                }
                if ($ditutup) {
                    throw ValidationException::withMessages(['status' => 'Tahun kegiatan sudah ditutup; perubahan hanya melalui pembukaan kembali jadwal.']);
                }
                if (! hash_equals($terkunci->versionToken(), (string) $data['expected_state'])) {
                    throw $this->basi();
                }
                if (! in_array($terkunci->status, ['rencana', 'tidak_terlaksana', 'terlaksana'], true)) {
                    throw ValidationException::withMessages(['status' => 'Realisasi tidak dapat dicatat pada kegiatan yang ditunda atau dibatalkan.']);
                }
                $transisi = ($data['tandai_terlaksana'] ?? false) && $terkunci->status !== 'terlaksana';
                $baru = array_intersect_key($data, array_flip(Kegiatan::REALISASI_FIELDS)) + array_fill_keys(Kegiatan::REALISASI_FIELDS, null);
                if ($transisi || $terkunci->status === 'terlaksana') {
                    $this->pastikanLengkap($baru);
                }
                if ($baru['tanggal_realisasi'] !== null) {
                    $this->pastikanDalamRentang($terkunci, (string) $baru['tanggal_realisasi']);
                }
                if ($transisi) {
                    $evaluasi = $this->persyaratan->evaluasi($terkunci, PersyaratanPerencanaanKegiatan::indikatorDiklaim((string) $terkunci->id),
                        null, PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN);
                    $kurang = PersyaratanPerencanaanKegiatan::kurang($evaluasi);
                    if ($kurang !== []) {
                        throw ValidationException::withMessages(['bukti' => 'Bukti pelaksanaan wajib belum lengkap: '.implode(', ', $kurang).'.']);
                    }
                }

                $lama = $this->realisasi($terkunci);
                $statusLama = $terkunci->status;
                $terkunci->forceFill($transisi ? [...$baru, 'status' => 'terlaksana'] : $baru)->save();
                $this->audit->catat(
                    actor: $aktor, tindakan: 'kegiatan.catat_realisasi', objekTipe: 'kegiatan', objekId: (string) $terkunci->id,
                    nilaiLama: $lama, nilaiBaru: $this->realisasi($terkunci), alasan: 'Pencatatan realisasi kegiatan.', dasarIzin: $decision->toAuditBasis(),
                );
                if ($transisi) {
                    // Token expected_versi RA terbuka harus basi, sama dengan transisi status lain.
                    RencanaAksi::whereIn('id', $headerIds)->whereIn('status_alur', RencanaAksi::STATUS_DAPAT_DISUNTING)->increment('versi');
                    $this->audit->catat(
                        actor: $aktor, tindakan: 'kegiatan.ubah_status', objekTipe: 'kegiatan', objekId: (string) $terkunci->id,
                        nilaiLama: ['status' => $statusLama], nilaiBaru: ['status' => 'terlaksana'],
                        alasan: 'Perubahan status pelaksanaan kegiatan.', dasarIzin: $decision->toAuditBasis(),
                    );
                }

                return ['terlaksana' => $transisi];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Dicatat sesudah rollback agar bukti penolakan tidak ikut terhapus.
            $this->catatPenolakan($actor, $kegiatan, $exception instanceof ValidationException ? $exception->validator->errors()->first() : $exception->getMessage(), $decision, $kurang);
            throw $exception;
        }
    }

    /**
     * Kegiatan terlaksana wajib bertanggal realisasi, berbiaya terpakai (`0` sah, melebihi anggaran tidak ditolak),
     * dan berpeserta riil (Q74 §74.2 butir 5, AC-3); setelah terlaksana nilainya dapat diubah tetapi tidak dikosongkan.
     *
     * @param  array<string, mixed>  $baru
     */
    private function pastikanLengkap(array $baru): void
    {
        $wajib = ['tanggal_realisasi' => 'Tanggal realisasi', 'biaya_terpakai' => 'Biaya terpakai', 'realisasi_peserta' => 'Peserta riil'];
        $kosong = array_filter($wajib, fn (string $label, string $field): bool => $baru[$field] === null, ARRAY_FILTER_USE_BOTH);
        if ($kosong !== []) {
            throw ValidationException::withMessages(array_map(fn (string $label): string => "{$label} wajib diisi untuk kegiatan terlaksana.", $kosong));
        }
    }

    /** Tanggal realisasi wajib di dalam rentang tanggal periode kegiatan pada tahun kegiatan (Q74 §74.2 butir 3, Q70 §70.1 butir 3). */
    private function pastikanDalamRentang(Kegiatan $kegiatan, string $tanggal): void
    {
        $periode = Periode::whereKey($kegiatan->periode_id)->sharedLock()->firstOrFail();
        $rentang = $periode->rentangPadaTahun((int) $kegiatan->tahun)
            ?? throw ValidationException::withMessages(['tanggal_realisasi' => "Tanggal periode {$periode->nama} belum diatur di Master Periode."]);
        if ($tanggal < $rentang['mulai'] || $tanggal > $rentang['selesai']) {
            throw ValidationException::withMessages(['tanggal_realisasi' => "Tanggal realisasi harus di dalam rentang {$periode->nama} {$kegiatan->tahun} ({$rentang['mulai']} sampai {$rentang['selesai']})."]);
        }
    }

    /** @return array<string, mixed> */
    private function realisasi(Kegiatan $kegiatan): array
    {
        return array_replace($kegiatan->only(Kegiatan::REALISASI_FIELDS), ['tanggal_realisasi' => $kegiatan->tanggal_realisasi?->format('Y-m-d')]);
    }

    private function basi(): ValidationException
    {
        return ValidationException::withMessages(['expected_state' => 'Kegiatan telah berubah. Muat ulang sebelum mencatat realisasi.'])->status(409);
    }
}
