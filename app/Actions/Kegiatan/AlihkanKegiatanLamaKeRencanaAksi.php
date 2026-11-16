<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;

/**
 * Tautan ubah lama diarahkan ke halaman kegiatan Rencana Aksi pemilik klaim
 * tertua (`created_at`, lalu `id`) agar tujuan deterministik ketika satu
 * kegiatan diklaim beberapa Rencana Aksi. Kegiatan tanpa klaim RA belum
 * tertaut, sehingga use case mengembalikan `null` dan pemanggil mengarahkan
 * ke daftar Rencana Aksi dengan penjelasan — bukan 404 — agar operator
 * mengetahui penautan manual diperlukan.
 *
 * Izin `kegiatan:read` pada unit kegiatan diperiksa lebih dulu: klaim hanya
 * dibaca setelah izin lolos, sehingga pengguna tanpa izin tidak dapat
 * memetakan kegiatan ke Rencana Aksi lewat header `Location`. Penolakan
 * diaudit sebagai `kegiatan.baca_ditolak` lalu dijawab 403 tanpa
 * mengungkap ada atau tidaknya klaim.
 */
class AlihkanKegiatanLamaKeRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    /** Id RA tujuan pengalihan; `null` bila kegiatan belum diklaim Rencana Aksi. */
    public function handle(User $actor, Kegiatan $kegiatan): ?string
    {
        $decision = $this->resolver->resolve($actor, PermissionCodes::KEGIATAN_READ, (string) $kegiatan->unit_id);

        if (! $decision->allowed) {
            $this->audit->catat(
                actor: $actor, tindakan: 'kegiatan.baca_ditolak', objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
                alasan: 'Izin membaca kegiatan tidak tersedia saat membuka tautan ubah lama.', dasarIzin: $decision->toAuditBasis(),
            );
            abort(403);
        }

        $rencanaAksi = KlaimKegiatan::query()
            ->where('kegiatan_id', $kegiatan->id)
            ->where('sumber_klaim', 'rencana_aksi')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('rencana_aksi_id');

        return $rencanaAksi === null ? null : (string) $rencanaAksi;
    }
}
