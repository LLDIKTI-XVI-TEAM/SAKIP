<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Q49 §49.1: nama kegiatan hingga 500 karakter dan tujuan opsional. */
    public function up(): void
    {
        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->string('nama', 500)->change();
            $table->text('tujuan')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('kegiatan')->whereNull('tujuan')->orWhereRaw('char_length(nama) > 255')->exists()) {
            throw new RuntimeException('Kegiatan memiliki tujuan kosong atau nama lebih dari 255 karakter. Rollback memerlukan keputusan koreksi data.');
        }

        Schema::table('kegiatan', function (Blueprint $table): void {
            $table->string('nama')->change();
            $table->text('tujuan')->nullable(false)->change();
        });
    }
};
