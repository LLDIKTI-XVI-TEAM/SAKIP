<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RegulasiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Katalog boleh disiapkan lebih awal, tetapi assignment role hanya boleh
        // dipasang oleh bootstrap pertama agar seluruh perubahan teraudit.
        $this->call(AccessCatalogSeeder::class);
    }
}
