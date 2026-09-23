<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at TYPE timestamp(6) without time zone');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at TYPE timestamp(6) without time zone');

        // Backfill timestamps for legacy rows to guarantee optimistic locking tokens exist
        DB::statement('UPDATE jenis_berkas SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        DB::statement('UPDATE jenis_berkas SET updated_at = created_at WHERE updated_at IS NULL');

        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at DROP DEFAULT');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at DROP DEFAULT');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN created_at TYPE timestamp(0) without time zone');
        DB::statement('ALTER TABLE jenis_berkas ALTER COLUMN updated_at TYPE timestamp(0) without time zone');
    }
};
