<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('berkas')
            ->where('berkasable_type', 'App\\Models\\RenstraPk')
            ->update(['berkasable_type' => 'renstra_pk']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('berkas')
            ->where('berkasable_type', 'renstra_pk')
            ->update(['berkasable_type' => 'App\\Models\\RenstraPk']);
    }
};
