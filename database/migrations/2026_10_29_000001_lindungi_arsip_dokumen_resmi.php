<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX berkas_arsip_satu_pengganti ON berkas (menggantikan_id) WHERE menggantikan_id IS NOT NULL AND berkasable_type IN ('jadwal_tahunan', 'jadwal_periode')");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION lindungi_arsip_dokumen_resmi() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') THEN
                        RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat dihapus';
                    END IF;
                    RETURN OLD;
                END IF;
                IF OLD.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') OR NEW.berkasable_type IN ('jadwal_tahunan', 'jadwal_periode') THEN
                    RAISE EXCEPTION 'Arsip dokumen resmi tidak dapat diubah';
                END IF;
                RETURN NEW;
            END;
            $$;
            CREATE TRIGGER berkas_arsip_tetap BEFORE UPDATE OR DELETE ON berkas
            FOR EACH ROW EXECUTE FUNCTION lindungi_arsip_dokumen_resmi();
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER berkas_arsip_tetap ON berkas');
        DB::statement('DROP FUNCTION lindungi_arsip_dokumen_resmi()');
        DB::statement('DROP INDEX berkas_arsip_satu_pengganti');
    }
};
