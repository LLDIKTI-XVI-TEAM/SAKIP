<?php

namespace App\Services\Jadwal;

use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use Carbon\CarbonInterface;

/** Definisi sesi bersama; metadata yang tidak lengkap tidak pernah membuka akses normal. */
class SesiKoreksiJadwal
{
    /** @param list<string> $periodeIds Periode yang hendak dimutasi; kosong untuk pemeriksaan header. */
    public static function berlaku(JadwalTahunan $jadwal, string $jenis, string $indikatorId, array $periodeIds = [], ?CarbonInterface $pada = null): bool
    {
        if (! self::berjalan($jadwal, $pada)) {
            return false;
        }
        $scope = $jadwal->lingkup_koreksi;

        return in_array($jenis, $scope['jenis_objek'], true) && in_array($indikatorId, $scope['indikator_ids'], true)
            && array_diff($periodeIds, $scope['periode_ids']) === [];
    }

    /** Sesi berstatus aktif, berjalan pada `$pada`, dan lingkupnya lengkap; dasar revisi jendela PIC di dalam sesi. */
    public static function berjalan(JadwalTahunan $jadwal, ?CarbonInterface $pada = null): bool
    {
        if ($jadwal->status !== 'aktif' || ! $jadwal->koreksi_mulai instanceof CarbonInterface || ! $jadwal->koreksi_sampai instanceof CarbonInterface
            || $jadwal->koreksi_mulai->gte($jadwal->koreksi_sampai)
            || ! ($pada ?? now())->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)) {
            return false;
        }
        $scope = $jadwal->lingkup_koreksi ?? [];
        foreach (['jenis_objek', 'indikator_ids', 'periode_ids'] as $key) {
            if (! is_array($scope[$key] ?? null) || ! array_is_list($scope[$key]) || $scope[$key] === []
                || count(array_filter($scope[$key], is_string(...))) !== count($scope[$key])) {
                return false;
            }
        }

        return true;
    }

    /** Rentang tanggal bisnis sesi (inklusif); jendela PIC resmi di dalam sesi wajib berada di dalamnya. @return array{0: string, 1: string} */
    public static function rentangTanggal(JadwalTahunan $jadwal): array
    {
        $zona = config('app.business_timezone');

        return [$jadwal->koreksi_mulai->copy()->setTimezone($zona)->toDateString(), $jadwal->koreksi_sampai->copy()->setTimezone($zona)->toDateString()];
    }

    /**
     * Jendela PIC dibuka resmi di dalam sesi bila seluruh tanggalnya berada di dalam rentang sesi dan berjalan pada `$pada`;
     * jendela lama yang dimulai sebelum sesi tidak membuka akses PIC. Lingkup periode diperiksa pemanggil lewat `berlaku()`.
     */
    public static function jendelaPicTerbuka(JadwalTahunan $jadwal, ?PeriodeJadwal $jendela, ?CarbonInterface $pada = null): bool
    {
        if (! $jendela || ! self::berjalan($jadwal, $pada)) {
            return false;
        }
        [$mulaiSesi, $akhirSesi] = self::rentangTanggal($jadwal);
        $hariIni = ($pada ?? now())->copy()->setTimezone(config('app.business_timezone'))->toDateString();
        $mulai = $jendela->pengisian_mulai->toDateString();
        $selesai = $jendela->pengisian_selesai->toDateString();

        return $mulai >= $mulaiSesi && $selesai <= $akhirSesi && $mulai <= $hariIni && $hariIni <= $selesai;
    }
}
