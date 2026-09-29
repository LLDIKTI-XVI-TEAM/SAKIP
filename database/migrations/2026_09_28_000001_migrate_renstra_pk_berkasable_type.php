<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Migrasi morfo-diskriminator berkas lama ke nilai kanonikal
        DB::table('berkas')
            ->where('berkasable_type', 'App\\Models\\RenstraPk')
            ->update(['berkasable_type' => 'renstra_pk']);

        // 2. Backfill created_at NULL dari riwayat audit_log objek terkait jika tersedia
        if (Schema::hasTable('audit_log')) {
            DB::statement("
                UPDATE renstra_pk
                SET created_at = sub.audit_waktu
                FROM (
                    SELECT objek_id, MIN(waktu) as audit_waktu
                    FROM audit_log
                    WHERE objek_tipe = 'renstra_pk' AND tindakan = 'renstra_pk.buat'
                    GROUP BY objek_id
                ) sub
                WHERE renstra_pk.id = sub.objek_id AND renstra_pk.created_at IS NULL
            ");

            // Backfill updated_at NULL dari riwayat audit_log terakhir jika tersedia
            DB::statement("
                UPDATE renstra_pk
                SET updated_at = sub.audit_waktu
                FROM (
                    SELECT objek_id, MAX(waktu) as audit_waktu
                    FROM audit_log
                    WHERE objek_tipe = 'renstra_pk'
                    GROUP BY objek_id
                ) sub
                WHERE renstra_pk.id = sub.objek_id AND renstra_pk.updated_at IS NULL
            ");
        }

        // 3. Fallback deterministik created_at dari tanggal_pk jika masih NULL
        DB::statement('
            UPDATE renstra_pk
            SET created_at = (tanggal_pk::timestamp)
            WHERE created_at IS NULL
        ');

        // 4. Fallback deterministik updated_at dari created_at jika masih NULL
        DB::statement('
            UPDATE renstra_pk
            SET updated_at = created_at
            WHERE updated_at IS NULL
        ');

        // 5. Tegakkan constraint NOT NULL pada created_at dan updated_at sesuai SAKIP Data Model §2.10
        Schema::table('renstra_pk', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable(false)->change();
            $table->timestamp('updated_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('renstra_pk', function (Blueprint $table) {
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });

        DB::table('berkas')
            ->where('berkasable_type', 'renstra_pk')
            ->update(['berkasable_type' => 'App\\Models\\RenstraPk']);
    }
};
