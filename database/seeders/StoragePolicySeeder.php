<?php

namespace Database\Seeders;

use App\Http\Controllers\Pengaturan\StoragePolicyController;
use App\Models\Pengaturan;
use Illuminate\Database\Seeder;

class StoragePolicySeeder extends Seeder
{
    /**
     * Jalankan seeder untuk 4 kunci kebijakan penyimpanan default.
     */
    public function run(): void
    {
        foreach (StoragePolicyController::POLICY_KEYS as $key => $meta) {
            Pengaturan::firstOrCreate(
                ['kunci' => $key],
                [
                    'nilai' => $meta['default'],
                    'tipe' => $meta['tipe'],
                    'grup' => 'berkas',
                    'updated_at' => now(),
                ]
            );
        }
    }
}
