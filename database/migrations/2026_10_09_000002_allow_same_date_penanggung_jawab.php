<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q34 §34.1: pergantian PJ pada tanggal sama diperbolehkan; pemenang per tanggal
     * ditentukan `urutan`, bukan created_at/UUID. Identity diisi selagi unique lama
     * masih aktif, sehingga baris existing tidak punya tanggal kembar dan urutan
     * pengisiannya tidak memengaruhi PJ efektif.
     */
    public function up(): void
    {
        Schema::table('penanggung_jawab', function (Blueprint $table): void {
            $table->bigInteger('urutan')->generatedAs()->always();
            $table->index(['indikator_id', 'tanggal_mulai_berlaku']);
            $table->dropUnique('penanggung_jawab_indikator_tanggal_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('penanggung_jawab')->select('indikator_id', 'tanggal_mulai_berlaku')
            ->groupBy('indikator_id', 'tanggal_mulai_berlaku')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Histori PJ memiliki tanggal kembar per indikator. Rollback memerlukan keputusan koreksi data.');
        }

        Schema::table('penanggung_jawab', function (Blueprint $table): void {
            $table->unique(['indikator_id', 'tanggal_mulai_berlaku'], 'penanggung_jawab_indikator_tanggal_unique');
            $table->dropIndex(['indikator_id', 'tanggal_mulai_berlaku']);
            $table->dropColumn('urutan');
        });
    }
};
