<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pengaturan ALTER COLUMN updated_at TYPE timestamp(6) without time zone');
            DB::statement('UPDATE pengaturan SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL');
            DB::statement('ALTER TABLE pengaturan ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pengaturan ALTER COLUMN updated_at DROP DEFAULT');
            DB::statement('ALTER TABLE pengaturan ALTER COLUMN updated_at TYPE timestamp(0) without time zone');
        }
    }
};
