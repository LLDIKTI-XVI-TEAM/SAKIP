<?php

use App\Services\Authorization\RoleCatalog;
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
        if (! Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->string('created_by_role', 50)
                    ->default('perencanaan')
                    ->nullable()
                    ->after('is_aktif');
            });
        }

        // 1. Backfill seluruh indikator legacy dengan default role 'perencanaan'
        DB::table('indikator_kinerjas')
            ->whereNull('created_by_role')
            ->update(['created_by_role' => 'perencanaan']);

        // 2. Normalisasi nilai di luar katalog role resmi ke 'perencanaan'
        DB::table('indikator_kinerjas')
            ->whereNotIn('created_by_role', RoleCatalog::codes())
            ->update(['created_by_role' => 'perencanaan']);

        // 3. Wajibkan non-null pada kolom created_by_role dengan default 'perencanaan'
        Schema::table('indikator_kinerjas', function (Blueprint $table) {
            $table->string('created_by_role', 50)
                ->default('perencanaan')
                ->nullable(false)
                ->change();
        });

        // 4. Tambahkan check constraint terhadap katalog role resmi
        $validRoles = implode("', '", RoleCatalog::codes());
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (
                SELECT 1 FROM pg_constraint WHERE conname = 'indikator_kinerjas_created_by_role_check'
            ) THEN
                ALTER TABLE indikator_kinerjas ADD CONSTRAINT indikator_kinerjas_created_by_role_check CHECK (created_by_role IN ('{$validRoles}'));
            END IF;
        END $$;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('indikator_kinerjas', 'created_by_role')) {
            DB::statement('ALTER TABLE indikator_kinerjas DROP CONSTRAINT IF EXISTS indikator_kinerjas_created_by_role_check');
            Schema::table('indikator_kinerjas', function (Blueprint $table) {
                $table->dropColumn('created_by_role');
            });
        }
    }
};
