<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // TRUNCATE tidak menjalankan trigger DELETE per baris.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_immutable_record_truncation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Versi pengajuan dan audit tidak boleh dikosongkan.' USING ERRCODE = '23514';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        foreach (['rencana_aksi_versi', 'pengukuran_versi', 'audit_log'] as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_no_truncate BEFORE TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION reject_immutable_record_truncation()");
        }
    }

    public function down(): void
    {
        foreach (['rencana_aksi_versi', 'pengukuran_versi', 'audit_log'] as $table) {
            DB::unprepared("DROP TRIGGER {$table}_no_truncate ON {$table}");
        }
        DB::unprepared('DROP FUNCTION reject_immutable_record_truncation()');
    }
};
