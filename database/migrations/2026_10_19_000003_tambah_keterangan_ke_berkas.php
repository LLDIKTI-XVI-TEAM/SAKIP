<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berkas', function (Blueprint $table): void {
            $table->text('keterangan')->nullable();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Kunci dulu agar keterangan baru tidak masuk di antara pemeriksaan dan penghapusan kolom.
            DB::statement('LOCK TABLE berkas IN ACCESS EXCLUSIVE MODE');
            if (DB::table('berkas')->whereNotNull('keterangan')->exists()) {
                throw new RuntimeException('Rollback ditolak: masih ada lampiran berketerangan, kolom tidak dihapus agar keterangan tidak hilang.');
            }
            Schema::table('berkas', function (Blueprint $table): void {
                $table->dropColumn('keterangan');
            });
        });
    }
};
