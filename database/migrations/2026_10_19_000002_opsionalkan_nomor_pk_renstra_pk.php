<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PK 2026 tidak bernomor (Q50 §50.1); keunikan per Renstra-tahun tetap dijaga unique yang sudah ada.
        DB::statement('ALTER TABLE renstra_pk ALTER COLUMN nomor_pk DROP NOT NULL');
    }

    public function down(): void
    {
        // PostgreSQL menolak bila masih ada PK tanpa nomor, sehingga rollback tidak mengarang nomor.
        DB::statement('ALTER TABLE renstra_pk ALTER COLUMN nomor_pk SET NOT NULL');
    }
};
