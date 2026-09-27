<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rentangTidakValid = DB::table('renstras')
            ->where('is_aktif', true)
            ->whereColumn('tahun_mulai', '>', 'tahun_selesai')
            ->exists();

        if ($rentangTidakValid) {
            throw new RuntimeException('Renstra aktif lama memiliki rentang tahun tidak valid. Tinjau tahun mulai dan selesai sebelum migrasi.');
        }

        $konflik = DB::table('renstras as pertama')
            ->join('renstras as kedua', 'pertama.id', '<', 'kedua.id')
            ->where('pertama.is_aktif', true)
            ->where('kedua.is_aktif', true)
            ->whereColumn('pertama.tahun_mulai', '<=', 'kedua.tahun_selesai')
            ->whereColumn('kedua.tahun_mulai', '<=', 'pertama.tahun_selesai')
            ->first(['pertama.id as id_pertama', 'kedua.id as id_kedua']);

        if ($konflik !== null) {
            throw new RuntimeException("Renstra aktif lama memiliki rentang tahun beririsan ({$konflik->id_pertama} dan {$konflik->id_kedua}). Tinjau dan putuskan statusnya sebelum migrasi.");
        }

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
