<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renstras', function (Blueprint $table) {
            $table->enum('status', ['draft', 'aktif', 'nonaktif', 'diarsipkan'])
                ->default('draft')
                ->after('is_aktif');
            $table->text('dasar_hukum')
                ->nullable()
                ->after('deskripsi');
            $table->foreignUuid('created_by')
                ->nullable()
                ->after('regulasi_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Skema lama tidak membedakan draft dari nonaktif; klasifikasi konservatif
        // mencegah baris historis kembali memperoleh hak hapus khusus draft.
        DB::statement("UPDATE renstras SET status = 'aktif' WHERE is_aktif = true");
        DB::statement("UPDATE renstras SET status = 'nonaktif' WHERE is_aktif = false OR is_aktif IS NULL");

        DB::statement("ALTER TABLE renstras ADD CONSTRAINT renstras_active_years_exclude EXCLUDE USING gist (int4range(tahun_mulai, tahun_selesai, '[]') WITH &&) WHERE (status = 'aktif')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE renstras DROP CONSTRAINT IF EXISTS renstras_active_years_exclude');

        Schema::table('renstras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['dasar_hukum', 'status']);
        });
    }
};
