<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun hanya berasal dari callback SSO; privilege awal dipasang bootstrap teraudit.
        $this->call(AccessCatalogSeeder::class);
    }
}
