<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            if (! Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
                $table->string('created_by_role', 50)
                    ->nullable()
                    ->after('is_aktif');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            if (Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
                $table->dropColumn('created_by_role');
            }
        });
    }
};
