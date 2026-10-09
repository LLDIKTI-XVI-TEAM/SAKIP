<?php

namespace Database\Factories;

use App\Models\RencanaAksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Header rencana aksi satu baris per kombinasi indikator × tahun.
 *
 * Kunci induk (indikator, unit, jadwal tahunan, PIC, pembuat) wajib dipasok
 * pemanggil secara eksplisit agar asal data selalu terlihat di test; factory
 * hanya menyediakan nilai bawaan deterministik untuk kolom sisanya.
 *
 * @extends Factory<RencanaAksi>
 */
class RencanaAksiFactory extends Factory
{
    protected $model = RencanaAksi::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'tahun' => 2026,
            'uraian' => null,
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'alasan_revisi' => null,
            'alasan_deviasi_pk' => null,
            'disahkan_at' => null,
            'disahkan_by' => null,
        ];
    }
}
