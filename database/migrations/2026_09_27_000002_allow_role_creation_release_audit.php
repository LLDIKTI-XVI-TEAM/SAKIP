<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->constraint(true);
    }

    public function down(): void
    {
        if (DB::table('audit_log')->where('sumber', 'preset_release')->where('tindakan', 'roles.tambah')->exists()) {
            throw new RuntimeException('Rollback ditolak: audit roles.tambah dari preset_release harus tetap dipertahankan.');
        }
        $this->constraint(false);
    }

    private function constraint(bool $creation): void
    {
        $roleEvents = "'role_permissions.ubah', 'roles.hapus'".($creation ? ", 'roles.tambah'" : '');
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_actor_provenance');
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_actor_provenance CHECK (
            (actor_type = 'user' AND sumber = 'manual' AND actor_id IS NOT NULL AND operator_reference IS NULL AND runtime_identity IS NULL)
            OR (actor_type = 'system' AND sumber = 'sso_onboarding' AND actor_id IS NULL AND operator_reference IS NULL AND runtime_identity IS NULL AND tindakan = 'user_roles.tambah')
            OR (actor_type = 'operator' AND sumber = 'bootstrap' AND actor_id IS NULL AND operator_reference IS NOT NULL AND runtime_identity IS NOT NULL AND length(trim(operator_reference)) > 0 AND length(trim(runtime_identity)) > 0)
            OR (actor_type = 'system' AND sumber = 'preset_release' AND actor_id IS NULL AND operator_reference IS NULL
                AND runtime_identity IS NOT NULL AND length(trim(runtime_identity)) > 0 AND alasan IS NOT NULL AND length(trim(alasan)) > 0
                AND ((tindakan IN ($roleEvents) AND objek_tipe = 'roles') OR (tindakan = 'permissions.ubah' AND objek_tipe = 'permissions')))
        )");
    }
};
