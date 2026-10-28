<?php

namespace App\Services\Jadwal;

use App\Models\JadwalTahunan;
use Carbon\CarbonInterface;

/** Definisi sesi bersama; metadata yang tidak lengkap tidak pernah membuka akses normal. */
class SesiKoreksiJadwal
{
    /** @param list<string> $periodeIds Periode yang hendak dimutasi; kosong untuk pemeriksaan header. */
    public static function berlaku(JadwalTahunan $jadwal, string $jenis, string $indikatorId, array $periodeIds = [], ?CarbonInterface $pada = null): bool
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

        return in_array($jenis, $scope['jenis_objek'], true) && in_array($indikatorId, $scope['indikator_ids'], true)
            && array_diff($periodeIds, $scope['periode_ids']) === [];
    }
}
