<?php

namespace Database\Factories;

use App\Models\RencanaAksiTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Satu baris target per kombinasi rencana aksi × periode × komponen.
 *
 * Baris manual memakai `komponen_id` NULL; `nilai` NULL berarti belum diisi
 * (berbeda dari 0 yang sah). Kunci induk wajib dipasok pemanggil; factory
 * hanya menyediakan nilai bawaan deterministik untuk kolom sisanya.
 *
 * @extends Factory<RencanaAksiTarget>
 */
class RencanaAksiTargetFactory extends Factory
{
    protected $model = RencanaAksiTarget::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'komponen_id' => null,
            'nilai' => null,
            'keterangan' => null,
            'updated_at' => now(),
        ];
    }
}
