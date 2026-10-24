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
use App\Services\Kegiatan\RollOverKegiatan;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UbahStatusKegiatan
{
    public function __construct(
        private readonly ResolveLockedActor $pengunci,
        private readonly KegiatanPolicy $policy,
        private readonly AuditLogger $audit,
        private readonly RollOverKegiatan $rollOver,
    ) {}

    /** Audit penolakan transisi; keputusan dihitung ulang dari Policy bila penolakan terjadi sebelum keputusan terkunci. */
    public function catatPenolakan(User $actor, Kegiatan $kegiatan, string $alasan, ?PermissionDecision $decision = null): void
    {
        $decision ??= $this->policy->keputusanSimpan($actor, $kegiatan, (string) $kegiatan->unit_id);
        $this->audit->catat(
            actor: $actor, tindakan: 'kegiatan.ubah_status_ditolak', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
            alasan: $alasan, dasarIzin: $decision->toAuditBasis(),
        );
    }

    /**
     * Satu use case transisi status pelaksanaan (Q69 §69.1) dengan justifikasi wajib. Kunci periode asal
     * dan status Rencana Aksi pengklaim tidak diperiksa (Q69 §69.4 butir 3); versi RA pengklaim yang masih
     * dapat disunting dinaikkan agar token pengajuannya basi. Urutan lock sama dengan `SimpanKegiatan`,
     * sehingga klaim tahap pengukuran kegiatan lanjutan (Q41 §41.4 butir 2, Q69 §69.5) dapat ditambahkan
     * dalam transaksi yang sama.
     *
     * @param  array<string, mixed>  $data  Hasil validasi `UbahStatusKegiatanRequest`.
     * @return array{status: string, periode_tujuan: string|null}
     */
    public function handle(User $actor, Kegiatan $kegiatan, array $data): array
    {
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $kegiatan, $data, &$decision): array {
                $locked = $this->pengunci->handle($actor, 'kegiatan:update');
                $aktor = $locked['aktor'];
                if (! $aktor instanceof User || $aktor->status !== 'aktif') {
                    $decision = $locked['keputusan'];
                    throw new AuthorizationException('Akun tidak aktif.');
                }
                Periode::lockConfiguration();
                // Header RA pengklaim dikunci sebelum jadwal dan kegiatan, searah gerbang klaim dan pengajuan RA.
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
                // Satu lanjutan per asal dijaga lock kegiatan + token + status final: `ditunda` tidak dapat ditunda lagi.
                $pilihan = Kegiatan::TRANSISI_STATUS[$terkunci->status]
                    ?? throw ValidationException::withMessages(['status' => 'Status kegiatan sudah final dan tidak dapat diubah.']);
                if (! in_array($data['status'], $pilihan, true)) {
                    throw ValidationException::withMessages(['status' => 'Status tujuan tidak tersedia untuk status kegiatan saat ini.']);
                }
                $periodeTujuan = $data['status'] === 'ditunda' ? $this->periodeTujuan($terkunci, (string) $data['periode_tujuan_id']) : null;

                $lama = $terkunci->only(['status', 'justifikasi']);
                $terkunci->forceFill(['status' => $data['status'], 'justifikasi' => $data['justifikasi']])->save();
                $lanjutan = $periodeTujuan ? $this->rollOver->buat($terkunci, $periodeTujuan, $aktor) : null;
                // Token expected_versi RA terbuka harus basi; RA yang sudah diajukan/diverifikasi/disahkan tidak disentuh.
                RencanaAksi::whereIn('id', $headerIds)->whereIn('status_alur', RencanaAksi::STATUS_DAPAT_DISUNTING)->increment('versi');
                $this->audit->catat(
                    actor: $aktor, tindakan: 'kegiatan.ubah_status', objekTipe: 'kegiatan', objekId: (string) $terkunci->id,
                    nilaiLama: $lama,
                    nilaiBaru: [...$terkunci->only(['status', 'justifikasi']), 'periode_tujuan_id' => $periodeTujuan?->id, 'kegiatan_lanjutan_id' => $lanjutan?->id],
                    alasan: 'Perubahan status pelaksanaan kegiatan.', dasarIzin: $decision->toAuditBasis(),
                );
                if ($lanjutan) {
                    $this->audit->catat(
                        actor: $aktor, tindakan: 'kegiatan.geser_periode', objekTipe: 'kegiatan', objekId: (string) $lanjutan->id,
                        nilaiBaru: ['kegiatan_asal_id' => $terkunci->id, 'periode_asal_id' => $terkunci->periode_id, 'periode_tujuan_id' => $periodeTujuan?->id],
                        alasan: 'Pembentukan kegiatan lanjutan dari kegiatan yang ditunda.', dasarIzin: $decision->toAuditBasis(),
                    );
                }

                return ['status' => $terkunci->status, 'periode_tujuan' => $periodeTujuan?->nama];
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Dicatat sesudah rollback agar bukti penolakan tidak ikut terhapus.
            $this->catatPenolakan($actor, $kegiatan, $exception instanceof ValidationException ? $exception->validator->errors()->first() : $exception->getMessage(), $decision);
            throw $exception;
        }
    }

    /**
     * Periode tujuan wajib aktif dan ber-`urutan` lebih besar dari periode asal (Q69 §69.5 butir 2); tahun sama
     * terpenuhi karena lanjutan menyalin tahun asal. Keanggotaan periode di jadwal tidak diperiksa karena kegiatan
     * tidak terikat jadwal, dan kunci periode tujuan bagi PIC bukan bagian transisi ini.
     */
    private function periodeTujuan(Kegiatan $kegiatan, string $tujuanId): Periode
    {
        // UUID PostgreSQL tidak peka huruf besar, sedangkan key collection peka.
        $tujuanId = strtolower($tujuanId);
        $periode = Periode::whereIn('id', [$kegiatan->periode_id, $tujuanId])->orderBy('id')->sharedLock()->get()->keyBy('id');
        $tujuan = $periode->get($tujuanId);
        if (! $tujuan || ! $tujuan->aktif || $tujuan->urutan <= $periode->get((string) $kegiatan->periode_id)?->urutan) {
            throw ValidationException::withMessages(['periode_tujuan_id' => 'Periode tujuan harus periode aktif yang lebih akhir dari periode kegiatan.']);
        }

        return $tujuan;
    }

    private function basi(): ValidationException
    {
        return ValidationException::withMessages(['expected_state' => 'Kegiatan telah berubah. Muat ulang sebelum mengubah status.'])->status(409);
    }
}
