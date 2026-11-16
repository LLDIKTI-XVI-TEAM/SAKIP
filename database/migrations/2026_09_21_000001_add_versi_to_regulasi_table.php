<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulasi', function (Blueprint $table): void {
            $table->unsignedInteger('versi')->default(1)->after('aktif');
        });
    }

    public function down(): void
    {
        Schema::table('regulasi', function (Blueprint $table): void {
            $table->dropColumn('versi');
        });
    }
};
