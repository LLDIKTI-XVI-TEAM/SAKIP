<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->constraints(true);
    }

    public function down(): void
    {
        // Histori rilis tidak boleh dibuang agar constraint lama bisa dipasang kembali.
        if (DB::table('audit_log')->where('sumber', 'preset_release')->exists()) {
            throw new RuntimeException('Rollback ditolak: audit preset_release harus tetap dipertahankan.');
        }
        $this->constraints(false);
    }

    private function constraints(bool $release): void
    {
        $sources = "'manual', 'sso_onboarding', 'bootstrap'".($release ? ", 'preset_release'" : '');
        $extra = $release ? "OR (actor_type = 'system' AND sumber = 'preset_release' AND actor_id IS NULL AND operator_reference IS NULL
            AND runtime_identity IS NOT NULL AND length(trim(runtime_identity)) > 0 AND alasan IS NOT NULL AND length(trim(alasan)) > 0
            AND ((tindakan = 'role_permissions.ubah' AND objek_tipe = 'roles') OR (tindakan = 'permissions.ubah' AND objek_tipe = 'permissions')
                OR (tindakan = 'roles.hapus' AND objek_tipe = 'roles')))" : '';
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_sumber_check, DROP CONSTRAINT audit_actor_provenance');
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_log_sumber_check CHECK (sumber IN ($sources)), ADD CONSTRAINT audit_actor_provenance CHECK (
            (actor_type = 'user' AND sumber = 'manual' AND actor_id IS NOT NULL AND operator_reference IS NULL AND runtime_identity IS NULL)
            OR (actor_type = 'system' AND sumber = 'sso_onboarding' AND actor_id IS NULL AND operator_reference IS NULL AND runtime_identity IS NULL AND tindakan = 'user_roles.tambah')
            OR (actor_type = 'operator' AND sumber = 'bootstrap' AND actor_id IS NULL AND operator_reference IS NOT NULL AND runtime_identity IS NOT NULL AND length(trim(operator_reference)) > 0 AND length(trim(runtime_identity)) > 0)
            $extra
        )");
    }
};
