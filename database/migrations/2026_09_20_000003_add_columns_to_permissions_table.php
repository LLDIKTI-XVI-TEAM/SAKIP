<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('kode', 100)->nullable()->unique();
            $table->string('entitas', 100)->nullable();
            $table->string('aksi', 100)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('butuh_scope', 20)->default('global'); // 'global' atau 'unit'
            $table->boolean('sensitif')->default(false);
            $table->boolean('aktif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn([
                'kode',
                'entitas',
                'aksi',
                'keterangan',
                'butuh_scope',
                'sensitif',
                'aktif',
            ]);
        });
    }
};
