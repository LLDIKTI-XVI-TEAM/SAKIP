<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengukuran_kinerjas', function (Blueprint $table) {
            $table->text('progres_kegiatan')->nullable();
            $table->text('kendala_masalah')->nullable();
            $table->text('strategi_tindak_lanjut')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pengukuran_kinerjas', fn (Blueprint $table) => $table->dropColumn(['progres_kegiatan', 'kendala_masalah', 'strategi_tindak_lanjut']));
    }
};
