<?php

namespace App\Actions\Auth;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleCatalog;
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
            $roles = Role::orderBy('id')->lockForUpdate()->get()->keyBy('kode');
            $expected = RoleCatalog::codes();
            sort($expected);
            if ($roles->pluck('kode')->sort()->values()->all() !== $expected || $roles->contains(fn (Role $role) => ! $role->aktif)) {
                throw new DomainException('Lima peran aktif wajib lengkap sebelum bootstrap.');
            }
            $superadmin = $roles->firstWhere('kode', 'superadmin');
            $assignment = $user ? DB::table('user_roles')->where('user_id', $user->id)->first() : null;
            // Bukti registrasi menggantikan asumsi assignment Pegawai; kandidat tidak boleh ditimpa.
            $onboarded = $user && DB::table('audit_log')->where('objek_id', $user->id)
                ->where('objek_tipe', 'users')->where('actor_type', 'system')->where('sumber', 'sso_onboarding')
                ->where('tindakan', 'pengguna.terdaftar')->where('nilai_baru->status', 'nonaktif')->exists();
            if (! $onboarded || $assignment || $user->status !== 'nonaktif'
                || DB::table('user_roles')->where('role_id', $superadmin->id)->exists()) {
                throw new DomainException('Keadaan awal bootstrap tidak sesuai; tidak ada data yang diubah.');
            }
            $provenance = ['actor_type' => 'operator', 'sumber' => 'bootstrap', 'operator_reference' => $operator, 'runtime_identity' => $runtimeIdentity, 'alasan' => $reason];
            $permissions = Permission::orderBy('id')->lockForUpdate()->get()->keyBy('kode');
            // Bootstrap hanya memvalidasi preset rilis; bukan jalur sinkronisasi atau pemulihan izin.
            foreach (RoleCatalog::codes() as $code) {
                $expected = RolePermissionPresets::forRole($code);
                sort($expected);
                $installed = DB::table('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                    ->where('role_id', $roles[$code]->id)->orderBy('permissions.kode')->pluck('permissions.kode')->all();
                if ($installed !== $expected || collect($expected)->contains(fn (string $permission) => ! ($permissions[$permission]->aktif ?? false))) {
                    throw new DomainException('Preset permission belum sesuai rilis; jalankan sinkronisasi katalog terlebih dahulu.');
                }
            }
            $audit = $this->audit->handle($provenance + ['tindakan' => 'user_roles.tambah', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_lama' => null, 'nilai_baru' => ['role_id' => $superadmin->id]]);
            DB::table('user_roles')->insert(['id' => Str::uuid(), 'user_id' => $user->id, 'role_id' => $superadmin->id, 'sumber_pemberian' => 'bootstrap', 'audit_id' => $audit->id, 'diberikan_oleh' => null, 'created_at' => now()]);
            $user->update(['status' => 'aktif']);
            $this->audit->handle($provenance + ['tindakan' => 'pengguna.aktivasi', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_lama' => ['status' => 'nonaktif'], 'nilai_baru' => ['status' => 'aktif']]);
            $completion = $this->audit->handle($provenance + ['tindakan' => 'auth.bootstrap', 'objek_tipe' => 'users', 'objek_id' => $user->id, 'nilai_baru' => ['role_id' => $superadmin->id, 'status' => 'aktif']]);
            DB::table('auth_bootstraps')->insert(['id' => 'initial', 'user_id' => $user->id, 'audit_id' => $completion->id, 'created_at' => now()]);

            return true;
        });
    }
}
