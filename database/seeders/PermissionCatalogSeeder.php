<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessCatalogSeeder::class);
    }
}
