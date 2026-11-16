<?php

namespace App\Actions\Kegiatan;

use App\Models\KlaimKegiatan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangKlaimKegiatan;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Hapus klaim tahap RA (PRD §16.5): hanya pembuat klaim atau Perencanaan,
 * wajib beralasan, dan hanya selama RA dapat disunting karena klaim ikut
 * dibekukan saat pengajuan. Baris dihapus; jejaknya tetap di audit.
 */
class HapusKlaimKegiatan
{
    public function __construct(
        private readonly GerbangKlaimKegiatan $gerbang,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Hasil berisi id RA klaim yang dihapus dan jumlah klaim tersisa untuk
     * pasangan (RA, kegiatan) itu, agar pemanggil dapat memilih tujuan
     * pengalihan ketika RA tersebut tidak lagi memuat kegiatan yang diubah.
     *
     * @return array{rencana_aksi_id: string, sisa_klaim: int}
     */
    public function handle(User $actor, string $kegiatanId, string $klaimId, string $alasan): array
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            return DB::transaction(function () use ($actor, $kegiatanId, $klaimId, $alasan, &$dasarIzin): array {
                // Klaim kegiatan lain dijawab 404 agar keberadaannya tidak bocor; RA dibaca dari klaim, bukan input.
                $headerId = KlaimKegiatan::whereKey($klaimId)->where('kegiatan_id', $kegiatanId)->firstOrFail()->rencana_aksi_id;
                $konteks = $this->gerbang->kunci($actor, (string) $headerId, $kegiatanId, PermissionCodes::KEGIATAN_UPDATE, [], $dasarIzin);
                $klaim = KlaimKegiatan::whereKey($klaimId)->where('kegiatan_id', $kegiatanId)->lockForUpdate()->firstOrFail();
                if ($klaim->sumber_klaim !== 'rencana_aksi') {
                    throw ValidationException::withMessages(['klaim' => 'Klaim tahap pengukuran mengikuti siklus pengukuran terkait dan tidak dapat dihapus di sini.']);
                }
                if ((string) $klaim->created_by !== (string) $konteks['pengunci']->id && ! $this->jendela->jalurPerencanaan($konteks['ra'])) {
                    throw new AuthorizationException('Klaim hanya dapat dihapus oleh pembuat klaim atau Perencanaan.');
                }

                $sebelum = TambahKlaimKegiatan::atributAudit($klaim);
                $klaim->delete();
                $konteks['header']->increment('versi');
                // Hitungan memakai pasangan yang sama dengan guard halaman ubah RA,
                // sehingga pemanggil tahu apakah konteks RA itu masih dapat dibuka.
                $sisaKlaim = KlaimKegiatan::where('rencana_aksi_id', $headerId)->where('kegiatan_id', $kegiatanId)->count();
                $this->audit->catat(
                    actor: $konteks['pengunci'],
                    tindakan: 'klaim_kegiatan.hapus',
                    objekTipe: 'klaim_kegiatan',
                    objekId: $klaimId,
                    nilaiLama: $sebelum,
                    alasan: AlasanAudit::sanitasi($alasan, 'Penghapusan klaim kegiatan.'),
                    dasarIzin: $dasarIzin,
                );

                return ['rencana_aksi_id' => (string) $headerId, 'sisa_klaim' => $sisaKlaim];
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)
                && KlaimKegiatan::whereKey($klaimId)->where('kegiatan_id', $kegiatanId)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'klaim_kegiatan.hapus_ditolak',
                    objekTipe: 'klaim_kegiatan',
                    objekId: $klaimId,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Penghapusan klaim kegiatan ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }
}
