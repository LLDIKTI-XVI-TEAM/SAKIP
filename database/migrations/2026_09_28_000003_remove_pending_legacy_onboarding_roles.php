<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const RUNTIME = 'migration:2026_09_28_000003_remove_pending_legacy_onboarding_roles';

    public function up(): void
    {
        // Jalankan saat writer aplikasi berhenti; audit dan pencabutan wajib atomik.
        DB::transaction(function () {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:initial-bootstrap']);
            $this->constraint(true);
            DB::table('user_roles as ur')
                ->join('users as u', 'u.id', '=', 'ur.user_id')
                ->join('roles as r', 'r.id', '=', 'ur.role_id')
                ->leftJoin('audit_log as a', 'a.id', '=', 'ur.audit_id')
                ->where('u.status', 'nonaktif')->where('r.kode', 'pegawai')
                ->where('ur.sumber_pemberian', 'sso_onboarding')
                ->select(['ur.id', 'ur.user_id'])
                ->selectRaw('row_to_json(ur)::text as assignment_snapshot')
                ->selectRaw("(ur.diberikan_oleh IS NULL AND a.actor_type = 'system' AND a.sumber = 'sso_onboarding'
                    AND a.actor_id IS NULL AND a.operator_reference IS NULL AND a.runtime_identity IS NULL
                    AND a.tindakan = 'user_roles.tambah' AND a.objek_tipe = 'users' AND a.objek_id = u.id
                    AND a.nilai_lama IS NULL AND a.nilai_baru->>'role_id' = ur.role_id::text
                    AND a.nilai_baru->'is_active' = 'false'::jsonb) as valid_provenance")
                ->lock('FOR UPDATE OF u, ur')
                ->chunkById(100, function ($assignments) {
                    foreach ($assignments as $assignment) {
                        if (! $assignment->valid_provenance) {
                            throw new RuntimeException('Migration dihentikan: provenance role onboarding legacy tidak cocok. Periksa data sebelum mencoba kembali.');
                        }
                        DB::table('audit_log')->insert([
                            'id' => Str::uuid(), 'actor_type' => 'system', 'sumber' => 'sso_onboarding',
                            'runtime_identity' => self::RUNTIME, 'waktu' => now(),
                            'tindakan' => 'user_roles.hapus', 'objek_tipe' => 'users', 'objek_id' => $assignment->user_id,
                            'nilai_lama' => $assignment->assignment_snapshot, 'nilai_baru' => null,
                            'alasan' => 'Penyelarasan Q32: akun onboarding lama yang belum aktif menunggu penetapan role eksplisit.',
                        ]);
                        DB::table('user_roles')->where('id', $assignment->id)->delete();
                    }
                }, 'ur.id', 'id');
        });
    }

    public function down(): void
    {
        // Mengembalikan pivot akan memulihkan akses tanpa keputusan Admin.
        if (DB::table('audit_log')->where('runtime_identity', self::RUNTIME)->exists()) {
            throw new RuntimeException('Rollback ditolak: audit corrective onboarding harus dipertahankan dan role tidak boleh dipulihkan otomatis.');
        }
        $this->constraint(false);
    }

    private function constraint(bool $cleanup): void
    {
        $corrective = $cleanup ? "OR (actor_type = 'system' AND sumber = 'sso_onboarding' AND actor_id IS NULL
            AND operator_reference IS NULL AND runtime_identity IS NOT NULL AND runtime_identity = '".self::RUNTIME."'
            AND tindakan = 'user_roles.hapus' AND objek_tipe = 'users' AND nilai_lama IS NOT NULL
            AND nilai_baru IS NULL AND alasan IS NOT NULL AND length(trim(alasan)) > 0)" : '';
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_actor_provenance');
        DB::statement("ALTER TABLE audit_log ADD CONSTRAINT audit_actor_provenance CHECK (
            (actor_type = 'user' AND sumber = 'manual' AND actor_id IS NOT NULL AND operator_reference IS NULL AND runtime_identity IS NULL)
            OR (actor_type = 'system' AND sumber = 'sso_onboarding' AND actor_id IS NULL AND operator_reference IS NULL AND runtime_identity IS NULL
                AND (tindakan = 'user_roles.tambah' OR (tindakan = 'pengguna.terdaftar' AND objek_tipe = 'users')))
            OR (actor_type = 'operator' AND sumber = 'bootstrap' AND actor_id IS NULL AND operator_reference IS NOT NULL AND runtime_identity IS NOT NULL
                AND length(trim(operator_reference)) > 0 AND length(trim(runtime_identity)) > 0)
            OR (actor_type = 'system' AND sumber = 'preset_release' AND actor_id IS NULL AND operator_reference IS NULL
                AND runtime_identity IS NOT NULL AND length(trim(runtime_identity)) > 0 AND alasan IS NOT NULL AND length(trim(alasan)) > 0
                AND ((tindakan IN ('role_permissions.ubah', 'roles.hapus', 'roles.tambah') AND objek_tipe = 'roles') OR (tindakan = 'permissions.ubah' AND objek_tipe = 'permissions')))
            $corrective
        )");
    }
};
