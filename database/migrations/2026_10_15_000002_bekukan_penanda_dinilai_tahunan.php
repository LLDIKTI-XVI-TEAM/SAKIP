<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indikator_kinerjas', fn (Blueprint $table) => $table->boolean('dinilai_tahunan')->default(false));
        Schema::table('jadwal_snapshot', fn (Blueprint $table) => $table->boolean('dinilai_tahunan')->default(false));

        // Guard kolom baru melengkapi guard konteks/komposisi existing tanpa mengubah aturan lock-nya.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_dinilai_tahunan_snapshot() RETURNS trigger AS $$
            BEGIN
                IF OLD.dinilai_tahunan IS DISTINCT FROM NEW.dinilai_tahunan THEN
                    RAISE EXCEPTION 'Penanda dinilai tahunan pada snapshot bersifat beku sejak terbit.' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
            CREATE TRIGGER jadwal_snapshot_dinilai_tahunan_immutable
                BEFORE UPDATE OF dinilai_tahunan ON jadwal_snapshot
                FOR EACH ROW EXECUTE FUNCTION guard_dinilai_tahunan_snapshot();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS jadwal_snapshot_dinilai_tahunan_immutable ON jadwal_snapshot');
        DB::statement('DROP FUNCTION IF EXISTS guard_dinilai_tahunan_snapshot()');
        Schema::table('jadwal_snapshot', fn (Blueprint $table) => $table->dropColumn('dinilai_tahunan'));
        Schema::table('indikator_kinerjas', fn (Blueprint $table) => $table->dropColumn('dinilai_tahunan'));
    }
};
