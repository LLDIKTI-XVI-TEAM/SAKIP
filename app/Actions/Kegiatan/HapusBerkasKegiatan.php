<?php

namespace App\Actions\Kegiatan;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Hapus berkas persyaratan tahap perencanaan kegiatan sebelum RA pengklaim
 * diajukan (Q42 §42.4 butir 5): soft delete beralasan (Q43 §43.2 butir 2),
 * file fisik dipertahankan karena versi RA dapat merujuknya. Berkas tahap
 * pelaksanaan dapat dihapus sampai kegiatan terlaksana (PRD §18.8).
 */
class HapusBerkasKegiatan
{
    public function __construct(
        private readonly GerbangBerkasKegiatan $gerbang,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, string $kegiatanId, string $berkasId, string $alasan): void
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;
        // Tahap dibaca sebelum lock hanya untuk memilih aturan kunci; berkas dicari ulang di bawah lock dengan tahap ini.
        $tahap = JenisBerkas::whereKey(BuktiDukung::whereKey($berkasId)->value('jenis_berkas_id'))->value('tahap') === PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN
            ? PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN : PersyaratanPerencanaanKegiatan::TAHAP;
        $label = $tahap === PersyaratanPerencanaanKegiatan::TAHAP ? 'perencanaan' : 'pelaksanaan';

        try {
            DB::transaction(function () use ($actor, $kegiatanId, $berkasId, $alasan, $tahap, $label, &$dasarIzin): void {
                ['pengunci' => $pengunci, 'kegiatan' => $kegiatan] = $this->gerbang->kunci($actor, $kegiatanId, PermissionCodes::BERKAS_DELETE, true, $dasarIzin, $tahap);
                // Berkas induk lain atau lampiran di luar tahap ini dijawab 404 agar keberadaannya tidak bocor.
                /** @var BuktiDukung $berkas */
                $berkas = PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $kegiatan->id)->whereKey($berkasId)
                    ->whereHas('jenisBerkas', fn ($q) => $q->where('tahap', $tahap))->lockForUpdate()->firstOrFail();
                $berkas->update(['dihapus_pada' => now(), 'dihapus_oleh' => $pengunci->id]);

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'berkas.hapus',
                    objekTipe: 'berkas',
                    objekId: (string) $berkas->id,
                    nilaiLama: [...GerbangBuktiRencanaAksi::metadataAudit($berkas), 'kegiatan_id' => (string) $kegiatan->id],
                    nilaiBaru: ['dihapus_pada' => $berkas->dihapus_pada?->toIso8601String()],
                    alasan: AlasanAudit::sanitasi($alasan, "Penghapusan berkas {$label} kegiatan."),
                    dasarIzin: $dasarIzin,
                );
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && Kegiatan::whereKey($kegiatanId)->exists()) {
                $this->audit->catat(actor: $actor, tindakan: 'berkas.hapus_ditolak', objekTipe: 'kegiatan', objekId: $kegiatanId,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), "Penghapusan berkas {$label} kegiatan ditolak."), dasarIzin: $dasarIzin);
            }

            throw $exception;
        }
    }
}
