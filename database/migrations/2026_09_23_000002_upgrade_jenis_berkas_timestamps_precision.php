<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at TYPE timestamp(6) without time zone');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at TYPE timestamp(6) without time zone');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at TYPE timestamp(0) without time zone');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at TYPE timestamp(0) without time zone');
    }
};
