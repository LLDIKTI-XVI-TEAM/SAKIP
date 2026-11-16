<?php

namespace App\Support;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\TargetRenstra;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * Pembaca (halaman Sasaran & Indikator) dan penulis (Action simpan) berbagi values/token yang sama, sehingga token props
 * cocok selama state tidak berubah. Urutan lock mengikuti TargetTahunanState::lock; transaksi dan izin milik Action.
 */
class TargetRenstraState
{
    /** @return array{indikator: IndikatorKinerja, sasaran: SasaranStrategis, renstra: Renstra, rows: Collection<int, TargetRenstra>} */
    public static function lock(string $indikatorId): array
    {
        $indikator = IndikatorKinerja::whereKey($indikatorId)->lockForUpdate()->firstOrFail();
        $sasaran = SasaranStrategis::whereKey($indikator->sasaran_strategis_id)->sharedLock()->firstOrFail();
        $renstra = Renstra::whereKey($sasaran->renstra_id)->sharedLock()->firstOrFail();
        $rows = TargetRenstra::where('indikator_id', $indikator->id)->lockForUpdate()->get()->keyBy('tahun');

        return ['indikator' => $indikator, 'sasaran' => $sasaran, 'renstra' => $renstra, 'rows' => $rows];
    }

    /** Baris yang sudah dikosongkan tetap muncul sebagai null. @param iterable<TargetRenstra> $rows @return array<int, ?string> */
    public static function values(iterable $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $values[$row->tahun] = TargetTahunanDecimal::normalize($row->nilai);
        }
        ksort($values);

        return $values;
    }

    /** @param array<int, ?string> $values */
    public static function token(IndikatorKinerja $indikator, Renstra $renstra, array $values): string
    {
        // Sengaja sempit dan tanpa status: target PK, nama, atau aktivasi Renstra tidak boleh memicu 409 palsu.
        // Status baca-saja sudah ditolak sebelum token diperiksa.
        $payload = ['indikator' => $indikator->only(['id', 'sasaran_strategis_id', 'presisi', 'tahun_mulai_berlaku']),
            'renstra' => $renstra->only(['id', 'tahun_mulai', 'tahun_selesai']), 'values' => $values];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), Crypt::getKey());
    }
}
