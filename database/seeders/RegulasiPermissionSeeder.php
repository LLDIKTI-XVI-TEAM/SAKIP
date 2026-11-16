<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RegulasiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Katalog dan preset mengikuti satu rilis atomik; assignment pengguna tidak berubah.
        $this->call(AccessCatalogSeeder::class);
    }
}
