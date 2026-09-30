<?php

namespace Database\Seeders;

use App\Models\Pengaturan;
use App\Services\Storage\StoragePolicyDefaults;
use Illuminate\Database\Seeder;

class StoragePolicySeeder extends Seeder
{
    /**
     * Jalankan seeder untuk default kebijakan storage.
     */
    public function run(): void
    {
        foreach (StoragePolicyDefaults::POLICY_KEYS as $key => $meta) {
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
