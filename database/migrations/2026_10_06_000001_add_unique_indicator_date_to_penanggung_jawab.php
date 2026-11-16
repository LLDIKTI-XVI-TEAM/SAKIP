<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('penanggung_jawab')->select('indikator_id', 'tanggal_mulai_berlaku')
            ->groupBy('indikator_id', 'tanggal_mulai_berlaku')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Histori PJ memiliki tanggal duplikat per indikator. Koreksi data memerlukan keputusan sebelum migrasi.');
        }

        Schema::table('penanggung_jawab', function (Blueprint $table): void {
            $table->unique(['indikator_id', 'tanggal_mulai_berlaku'], 'penanggung_jawab_indikator_tanggal_unique');
            $table->dropIndex(['indikator_id', 'tanggal_mulai_berlaku']);
        });
    }

    public function down(): void
    {
        Schema::table('penanggung_jawab', function (Blueprint $table): void {
            $table->index(['indikator_id', 'tanggal_mulai_berlaku']);
            $table->dropUnique('penanggung_jawab_indikator_tanggal_unique');
        });
    }
};
