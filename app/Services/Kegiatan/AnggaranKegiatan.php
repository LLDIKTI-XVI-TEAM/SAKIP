<?php

namespace App\Services\Kegiatan;

use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Services\RencanaAksi\KesiapanPengajuan;
use App\Services\RencanaAksi\PeriodeTanpaTarget;

/**
 * Anggaran kegiatan wajib sejak Q42 §42.2 butir 2. Kosong hanya sah untuk
 * kegiatan data awal 2026 yang dicatat lewat jalur Perencanaan dan kegiatan
 * pada periode lampau (Q46 §46.1 butir 3); kosong berarti "belum diisi", bukan 0.
 */
class AnggaranKegiatan
{
    public const PESAN_WAJIB = 'Anggaran kegiatan wajib diisi; isi 0 bila kegiatan tanpa anggaran.';

    public function bolehKosong(int $tahun, string $periodeId, bool $jalurPerencanaan): bool
    {
        // Pengenal data awal sama dengan gerbang pengajuan RA (keputusan PM 16 Oktober 2026).
        if ($tahun === KesiapanPengajuan::TAHUN_DATA_AWAL && $jalurPerencanaan) {
            return true;
        }

        return self::periodeLampau($tahun, $periodeId);
    }

    /** Kegiatan tidak bertaut jadwal: periode lampau bila lampau pada jadwal tahun itu mana pun (Q46 §46.1, hanya 2026). */
    public static function periodeLampau(int $tahun, string $periodeId): bool
    {
        return JadwalTahunan::where('tahun', $tahun)->where('status', '!=', 'draft')->get()
            ->contains(fn (JadwalTahunan $jadwal): bool => PeriodeTanpaTarget::isPeriodeLampau(
                $jadwal, PeriodeJadwal::where('jadwal_id', $jadwal->id)->where('periode_id', $periodeId)->first(),
            ));
    }
}
