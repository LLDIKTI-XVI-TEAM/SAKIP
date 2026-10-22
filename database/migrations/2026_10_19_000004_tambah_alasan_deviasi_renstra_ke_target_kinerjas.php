<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_kinerjas', function (Blueprint $table): void {
            $table->text('alasan_deviasi_renstra')->nullable();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Kunci dulu agar alasan baru tidak masuk di antara pemeriksaan dan penghapusan kolom.
            DB::statement('LOCK TABLE target_kinerjas IN ACCESS EXCLUSIVE MODE');
            if (DB::table('target_kinerjas')->whereNotNull('alasan_deviasi_renstra')->exists()) {
                throw new RuntimeException('Rollback ditolak: masih ada alasan deviasi target Renstra, kolom tidak dihapus agar alasan tidak hilang.');
            }
            Schema::table('target_kinerjas', function (Blueprint $table): void {
                $table->dropColumn('alasan_deviasi_renstra');
            });
        });
    }
};
