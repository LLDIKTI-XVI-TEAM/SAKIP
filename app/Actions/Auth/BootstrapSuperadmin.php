<?php

namespace App\Actions\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RolePermissionPresets;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BootstrapSuperadmin
{
    public function __construct(private WriteAuditLog $audit) {}

    public function handle(string $userId, string $operator, string $reason, string $runtimeIdentity): bool
    {
        if (! Str::isUuid($userId)) {
            throw new DomainException('ID akun SAKIP tidak valid.');
        }
        if (trim($operator) === '' || trim($reason) === '' || trim($runtimeIdentity) === '') {
            throw new DomainException('Operator, konteks eksekusi dan alasan wajib diisi.');
        }

        return DB::transaction(function () use ($userId, $operator, $reason, $runtimeIdentity) {
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:initial-bootstrap']);
            $marker = DB::table('auth_bootstraps')->where('id', 'initial')->first();
            $user = User::whereKey($userId)->lockForUpdate()->first();
            if ($marker) {
                if (! $user || $marker->user_id !== $user->id) {
                    throw new DomainException('Bootstrap telah diselesaikan untuk akun lain.');
                }

                return false;
            }
            $roles = Role::orderBy('urutan')->get();
            $expected = ['admin', 'pegawai', 'perencanaan', 'pic', 'pimpinan', 'superadmin'];
            if ($roles->pluck('kode')->sort()->values()->all() !== $expected || $roles->contains(fn (Role $role) => ! $role->aktif)) {
                throw new DomainException('Enam peran aktif wajib lengkap sebelum bootstrap.');
            }
            $superadmin = $roles->firstWhere('kode', 'superadmin');
            $pegawai = $roles->firstWhere('kode', 'pegawai');
            $assignment = $user ? DB::table('user_roles')->where('user_id', $user->id)->first() : null;
            $onboarded = $assignment && $assignment->sumber_pemberian === 'sso_onboarding'
                && $assignment->role_id === $pegawai->id
                && DB::table('audit_log')->where('id', $assignment->audit_id)->where('objek_id', $user->id)->where('sumber', 'sso_onboarding')->where('tindakan', 'user_roles.tambah')->exists();
            if (! $onboarded || $user->is_active
                || DB::table('role_permissions')->exists()
                || DB::table('user_roles')->where('role_id', $superadmin->id)->exists()) {
                throw new DomainException('Keadaan awal bootstrap tidak sesuai; tidak ada data yang diubah.');
            }
            $provenance = ['actor_type' => 'operator', 'sumber' => 'bootstrap', 'operator_reference' => $operator, 'runtime_identity' => $runtimeIdentity, 'alasan' => $reason];
            $permissions = Permission::where('aktif', true)->pluck('id', 'kode');
            foreach ($roles as $role) {
                // Preset PIC belum diputuskan pada Q31; role tersedia tanpa izin bawaan.
                if ($role->kode === 'pic') {
                    continue;
                }
                $codes = RolePermissionPresets::forRole($role->kode);
                foreach ($codes as $code) {
                    if (! isset($permissions[$code])) {
                        throw new DomainException('Katalog permission bootstrap belum lengkap.');
                    }
                    DB::table('role_permissions')->insert(['id' => Str::uuid(), 'role_id' => $role->id, 'permission_id' => $permissions[$code], 'created_at' => now()]);
                }
                $this->audit->handle($provenance + ['tindakan' => 'role_permissions.ubah', 'objek_tipe' => 'roles', 'objek_id' => $role->id, 'nilai_lama' => [], 'nilai_baru' => ['permissions' => $codes]]);
            }
            $audit = $this->audit->handle($provenance + ['tindakan' => 'user_roles.ubah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_lama' => ['role_id' => $assignment->role_id], 'nilai_baru' => ['role_id' => $superadmin->id]]);
            DB::table('user_roles')->where('id', $assignment->id)->update(['role_id' => $superadmin->id, 'sumber_pemberian' => 'bootstrap', 'audit_id' => $audit->id]);
            $user->update(['is_active' => true]);
            $this->audit->handle($provenance + ['tindakan' => 'pengguna.aktivasi', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_lama' => ['is_active' => false], 'nilai_baru' => ['is_active' => true]]);
            $completion = $this->audit->handle($provenance + ['tindakan' => 'auth.bootstrap', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_baru' => ['role_id' => $superadmin->id, 'is_active' => true]]);
            DB::table('auth_bootstraps')->insert(['id' => 'initial', 'user_id' => $user->id, 'audit_id' => $completion->id, 'created_at' => now()]);

            return true;
        });
    }
}
