<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX unit_nama_lower_unique ON unit (LOWER(nama))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unit_nama_lower_unique');
    }
};
