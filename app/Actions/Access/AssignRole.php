<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentWarnings;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssignRole
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit, private RoleAssignmentWarnings $warnings) {}

    /**
     * @param  array{id:string,role_id:string,audit_id:?string}|null  $expectedAssignment
     * @return array{status:'assigned'|'changed'|'unchanged',has_active_pj:bool}
     */
    public function handle(User $actor, string $targetId, string $roleId, string $reason, ?array $expectedAssignment): array
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi dan maksimal 2.000 karakter.']);
        }
        $result = DB::transaction(function () use ($actor, $targetId, $roleId, $reason, $expectedAssignment) {
            // Sentinel bersama ChangeUserStatus: daftar superadmin dibaca setelah promosi/penurunan lain commit, sebelum kunci baris.
            DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:superadmin-aktif']);
            $superadmins = fn () => DB::table('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('roles.kode', 'superadmin')->where('roles.aktif', true);
            // Kunci user juga saat pivot belum ada; seluruh superadmin ikut dikunci dalam urutan yang sama dengan ChangeUserStatus agar guard superadmin terakhir tahan balapan.
            $users = User::whereIn('id', array_values(array_unique([$actor->id, $targetId, ...$superadmins()->pluck('user_roles.user_id')->all()])))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $currentActor = $users->get($actor->id) ?? User::whereKey($actor->id)->firstOrFail();
            // Kunci role aktor dan tujuan dalam satu urutan, selaras writer preset; jangan mengunci aktor dahulu secara terpisah.
            $roleIds = DB::table('user_roles')->where('user_id', $currentActor->id)->pluck('role_id')->all();
            if (Str::isUuid($roleId)) {
                $roleIds[] = $roleId;
            }
            $roles = Role::whereIn('id', $roleIds)->orderBy('id')->sharedLock()->get()->keyBy('id');
            $decisions = [
                'pengguna_read' => $this->permissions->decide($currentActor, 'pengguna:read'),
                'akses_update' => $this->permissions->decide($currentActor, 'akses:update'),
            ];
            $audit = ['actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual', 'objek_tipe' => 'users', 'objek_id' => $targetId, 'dasar_izin' => $decisions];
            if (! $decisions['pengguna_read']['allowed'] || ! $decisions['akses_update']['allowed']) {
                return ['denied' => true, 'audit' => $audit, 'message' => 'Anda tidak berwenang menetapkan peran.'];
            }
            $target = $users->get($targetId) ?? User::whereKey($targetId)->firstOrFail();
            $role = $roles->get(strtolower($roleId));
            if (! $role || ! $role->aktif || ! RoleCatalog::contains($role->kode)) {
                return ['field' => 'role_id', 'audit' => $audit, 'message' => 'Pilih peran resmi yang aktif.'];
            }
            $assignment = DB::table('user_roles')->where('user_id', $target->id)->first();
            $state = $assignment ? ['id' => $assignment->id, 'role_id' => $assignment->role_id, 'audit_id' => $assignment->audit_id] : null;
            // Nilai token dibandingkan ketat, tetapi urutan key JSON tidak bermakna.
            $matches = $state === null ? $expectedAssignment === null : $expectedAssignment !== null
                && count($expectedAssignment) === 3
                && ($expectedAssignment['id'] ?? null) === $state['id']
                && ($expectedAssignment['role_id'] ?? null) === $state['role_id']
                && array_key_exists('audit_id', $expectedAssignment)
                && $expectedAssignment['audit_id'] === $state['audit_id'];
            if (! $matches) {
                return ['field' => 'expected_assignment', 'audit' => $audit, 'message' => 'Peran pengguna telah berubah. Muat ulang data sebelum menyimpan.'];
            }
            // Dibaca ulang setelah kunci: penurunan atau penonaktifan yang commit lebih dulu tidak boleh terhitung sebagai superadmin lain.
            $superadminIds = $superadmins()->whereIn('user_roles.user_id', $users->keys()->all())->pluck('user_roles.user_id')->all();
            $otherActiveSuperadmin = $users->only($superadminIds)->except($target->id)->contains(fn (User $user) => $user->status === 'aktif');
            if ($role->kode !== 'superadmin' && $target->status === 'aktif' && in_array($target->id, $superadminIds, true) && ! $otherActiveSuperadmin) {
                return ['field' => 'role_id', 'audit' => $audit, 'message' => 'Superadmin aktif terakhir tidak dapat diturunkan perannya.'];
            }
            // Snapshot dibaca setelah izin/token sah dan sebelum write; kegagalan query membatalkan transaksi.
            $hasActivePj = $this->warnings->forUsers([$target->id])[$target->id];
            if ($assignment && $assignment->role_id === $role->id) {
                return ['status' => 'unchanged', 'has_active_pj' => $hasActivePj];
            }
            $before = $assignment ? ['role_id' => $assignment->role_id, 'role_kode' => Role::whereKey($assignment->role_id)->value('kode')] : null;
            $entry = $this->audit->handle($audit + [
                'tindakan' => $assignment ? 'user_roles.ubah' : 'user_roles.tambah', 'alasan' => $reason,
                'nilai_lama' => $before, 'nilai_baru' => ['role_id' => $role->id, 'role_kode' => $role->kode],
            ]);
            $values = ['role_id' => $role->id, 'diberikan_oleh' => $currentActor->id, 'sumber_pemberian' => 'manual', 'audit_id' => $entry->id];
            if ($assignment) {
                DB::table('user_roles')->where('id', $assignment->id)->update($values);
            } else {
                DB::table('user_roles')->insert($values + ['id' => (string) Str::uuid(), 'user_id' => $target->id, 'created_at' => now()]);
            }

            return ['status' => $assignment ? 'changed' : 'assigned', 'has_active_pj' => $hasActivePj];
        });
        if (isset($result['audit'])) {
            // Hanya Action memiliki audit denial; penolakan tidak hilang karena rollback mutasi.
            $this->audit->handle($result['audit'] + ['tindakan' => 'user_roles.ditolak', 'alasan' => $result['message']]);
            if (isset($result['denied'])) {
                throw new AuthorizationException($result['message']);
            }
            throw ValidationException::withMessages([$result['field'] => $result['message']]);
        }

        return $result;
    }
}
