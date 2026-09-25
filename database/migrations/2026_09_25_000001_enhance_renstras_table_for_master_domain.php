<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renstras', function (Blueprint $table) {
            $table->enum('status', ['draft', 'aktif', 'nonaktif', 'diarsipkan'])
                ->default('draft')
                ->after('is_aktif');
            $table->text('dasar_hukum')
                ->nullable()
                ->after('deskripsi');
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('regulasi_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Sinkronkan status baris renstra existing berdasarkan is_aktif.
        DB::statement("UPDATE renstras SET status = 'aktif' WHERE is_aktif = true");
        DB::statement("UPDATE renstras SET status = 'draft' WHERE is_aktif = false OR is_aktif IS NULL");
    }

    public function down(): void
    {
        Schema::table('renstras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['dasar_hukum', 'status']);
        });
    }
};
