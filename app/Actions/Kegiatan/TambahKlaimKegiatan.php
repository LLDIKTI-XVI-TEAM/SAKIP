<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Services\Kegiatan\RollOverKegiatan;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Klaim kegiatan existing ke Rencana Aksi pada tahap RA (PRD §16, Workflow §9).
 * Klaim hanya dokumentasi dukungan dan arah dampak: tidak menulis nilai
 * komponen maupun pengukuran (PRD §16.3).
 */
class TambahKlaimKegiatan
{
    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly KonteksBekuRencanaAksi $konteks,
        private readonly AuditLogger $audit,
        private readonly RollOverKegiatan $rollOver,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Hasil validasi `TambahKlaimKegiatanRequest`.
     */
    public function handle(User $actor, string $kegiatanId, array $data): KlaimKegiatan
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;
        $headerId = strtolower((string) $data['rencana_aksi_id']);

        try {
            return DB::transaction(function () use ($kegiatanId, $data, $headerId, $actor, &$dasarIzin): KlaimKegiatan {
                $konteks = $this->gerbang->kunci($actor, $headerId, $kegiatanId, PermissionCodes::KEGIATAN_UPDATE, [], $dasarIzin);
                /** @var Kegiatan $kegiatan */
                $kegiatan = $konteks['kegiatan'];
                $komponenId = isset($data['komponen_id']) ? strtolower((string) $data['komponen_id']) : null;
                $this->pastikanCocok($konteks['header'], $kegiatan, $komponenId);

                $nilai = [
                    'rencana_aksi_id' => (string) $konteks['header']->id,
                    'kegiatan_id' => (string) $kegiatan->id,
                    'komponen_id' => $komponenId,
                    'arah_dampak' => (string) $data['arah_dampak'],
                    'catatan' => $data['catatan'] ?? null,
                ];
                if (KlaimKegiatan::where('rencana_aksi_id', $nilai['rencana_aksi_id'])->where('kegiatan_id', $nilai['kegiatan_id'])
                    ->where('komponen_id', $komponenId)->exists()) {
                    throw ValidationException::withMessages(['komponen_id' => 'Kegiatan ini sudah diklaim ke rencana aksi dan komponen yang sama.']);
                }

                try {
                    $klaim = KlaimKegiatan::create($nilai + ['sumber_klaim' => 'rencana_aksi', 'created_by' => $konteks['pengunci']->id, 'created_at' => now()]);
                } catch (UniqueConstraintViolationException) {
                    // Index unik COALESCE(komponen_id) menjadi penjaga terakhir untuk race dua klaim identik.
                    throw ValidationException::withMessages(['komponen_id' => 'Kegiatan ini sudah diklaim ke rencana aksi dan komponen yang sama.']);
                }

                $konteks['header']->increment('versi');

                $this->audit->catat(
                    actor: $konteks['pengunci'],
                    tindakan: 'klaim_kegiatan.tambah',
                    objekTipe: 'klaim_kegiatan',
                    objekId: (string) $klaim->id,
                    nilaiBaru: self::atributAudit($klaim),
                    alasan: 'Klaim kegiatan terhadap rencana aksi.',
                    dasarIzin: $dasarIzin,
                );
                // Klaim tetap boleh pada setiap status (Q69 §69.3 butir 3): lanjutan yang baris pengukurannya sudah ada ikut diklaim.
                foreach ($this->rollOver->klaimTurunan($kegiatan, $konteks['header'], $konteks['pengunci']) as $baris) {
                    $this->audit->catat(
                        actor: $konteks['pengunci'], tindakan: 'klaim_kegiatan.tambah', objekTipe: 'klaim_kegiatan', objekId: (string) $baris['klaim']->id,
                        nilaiBaru: [...self::atributAudit($baris['klaim']), 'pengukuran_id' => $baris['klaim']->pengukuran_id,
                            'kegiatan_asal_id' => $baris['kegiatan_asal_id']],
                        alasan: 'Klaim tahap pengukuran kegiatan lanjutan.', dasarIzin: $dasarIzin,
                    );
                }

                return $klaim;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && Kegiatan::whereKey($kegiatanId)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'klaim_kegiatan.tambah_ditolak',
                    objekTipe: 'kegiatan',
                    objekId: $kegiatanId,
                    nilaiBaru: ['rencana_aksi_id' => $headerId],
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Klaim kegiatan terhadap rencana aksi ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }

    /**
     * Konteks kegiatan harus sama dengan RA: unit dan tahun sama, periode
     * termasuk periode efektif RA, dan komponen (opsional) anggota snapshot
     * beku yang sama dengan yang dipakai `SubmitPengukuran` untuk memvalidasi klaim.
     *
     * @throws ValidationException
     */
    public function pastikanCocok(RencanaAksi $header, Kegiatan $kegiatan, ?string $komponenId, string $fieldPeriode = 'rencana_aksi_id'): void
    {
        if ((string) $kegiatan->unit_id !== (string) $header->unit_id) {
            throw ValidationException::withMessages(['rencana_aksi_id' => 'Klaim lintas unit tidak diperbolehkan: unit kegiatan berbeda dengan unit rencana aksi.']);
        }
        if ((int) $kegiatan->tahun !== (int) $header->tahun) {
            throw ValidationException::withMessages(['rencana_aksi_id' => 'Tahun kegiatan berbeda dengan tahun rencana aksi.']);
        }
        $snapshot = $this->konteks->snapshotTerbaru((string) $header->jadwal_tahunan_id, (string) $header->indikator_id)
            ?? throw ValidationException::withMessages(['rencana_aksi_id' => 'Konteks indikator beku untuk rencana aksi ini tidak tersedia.']);
        // Sama dengan halaman dan pengajuan RA: header yang unitnya tidak selaras dengan snapshot terbaru ditolak fail-closed.
        if ((string) $snapshot->unit_id !== (string) $header->unit_id) {
            throw ValidationException::withMessages(['rencana_aksi_id' => 'Unit pemilik rencana aksi tidak selaras dengan konteks beku terbaru.']);
        }
        $periodeEfektif = $this->konteks->periodeEfektif($snapshot, $this->konteks->jendela((string) $header->jadwal_tahunan_id));
        if (! $periodeEfektif->contains((string) $kegiatan->periode_id)) {
            throw ValidationException::withMessages([$fieldPeriode => 'Periode kegiatan tidak termasuk periode efektif rencana aksi.']);
        }
        if ($komponenId !== null && ! $this->konteks->komponen($snapshot)->pluck('komponen_id')->contains($komponenId)) {
            throw ValidationException::withMessages(['komponen_id' => 'Komponen bukan anggota indikator beku rencana aksi ini.']);
        }
    }

    /** @return array<string, mixed> */
    public static function atributAudit(KlaimKegiatan $klaim): array
    {
        return $klaim->only(['rencana_aksi_id', 'kegiatan_id', 'komponen_id', 'arah_dampak', 'catatan', 'sumber_klaim', 'created_by']);
    }
}
