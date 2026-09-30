<?php

namespace Database\Seeders;

use App\Services\Storage\StoragePolicyDefaults;
use Illuminate\Database\Seeder;

class StoragePolicySeeder extends Seeder
{
    /**
     * Jalankan seeder untuk default kebijakan storage.
     */
    public function run(StoragePolicyDefaults $defaults): void
    {
        $defaults->ensure();
    }
}
