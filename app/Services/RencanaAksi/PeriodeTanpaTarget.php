<?php

namespace App\Services\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class PeriodeTanpaTarget
{
    /** Pengecualian Q46 hanya untuk jadwal 2026; lampau ditentukan saat aktivasi, bukan saat pengajuan. */
    public static function isPeriodeLampau(JadwalTahunan $jadwal, ?PeriodeJadwal $periode): bool
    {
        if ($jadwal->tahun !== 2026 || ! $jadwal->activated_at || ! $periode || $periode->jadwal_id !== $jadwal->id) {
            return false;
        }
        $tanggalAktivasi = CarbonImmutable::parse($jadwal->activated_at, 'UTC')->setTimezone('Asia/Makassar')->toDateString();

        return $periode->pengisian_selesai->toDateString() < $tanggalAktivasi;
    }

    /** @param Collection<int, PeriodeJadwal> $jendela */
    public static function bolehTanpaTarget(JadwalSnapshot $snapshot, PeriodeJadwal $periode, Collection $jendela): bool
    {
        if ($periode->jadwal_id !== $snapshot->jadwal_id) {
            return false;
        }
        if (self::isPeriodeLampau($snapshot->jadwal, $periode)) {
            return true;
        }
        $final = $jendela->first(fn (PeriodeJadwal $window): bool => $window->jadwal_id === $snapshot->jadwal_id && (bool) $window->periode?->is_nilai_akhir);

        return $snapshot->dinilai_tahunan && $periode->periode && $final?->periode
            && $periode->periode->urutan < $final->periode->urutan;
    }
}
