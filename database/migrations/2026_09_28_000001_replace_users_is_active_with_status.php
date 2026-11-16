<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Cutover transaksional: writer lama harus dihentikan sebelum migration dijalankan.
    public function up(): void
    {
        DB::statement('ALTER TABLE users ALTER COLUMN is_active DROP DEFAULT');
        DB::statement("ALTER TABLE users ALTER COLUMN is_active TYPE varchar(255) USING CASE WHEN is_active THEN 'aktif' ELSE 'nonaktif' END");
        DB::statement('ALTER TABLE users RENAME COLUMN is_active TO status');
        DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'nonaktif', ALTER COLUMN status SET NOT NULL, ADD CONSTRAINT users_status_check CHECK (status IN ('aktif', 'nonaktif'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_status_check, ALTER COLUMN status DROP DEFAULT');
        DB::statement("ALTER TABLE users ALTER COLUMN status TYPE boolean USING (status = 'aktif')");
        DB::statement('ALTER TABLE users RENAME COLUMN status TO is_active');
        DB::statement('ALTER TABLE users ALTER COLUMN is_active SET DEFAULT false, ALTER COLUMN is_active SET NOT NULL');
    }
};
