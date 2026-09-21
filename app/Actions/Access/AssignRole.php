<?php

namespace App\Actions\Access;

use App\Actions\Audit\WriteAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssignRole
{
    public function __construct(private PermissionResolver $permissions, private WriteAuditLog $audit) {}

    /**
     * @param  array{id:string,role_id:string,audit_id:?string}|null  $expectedAssignment
     * @return 'assigned'|'changed'|'unchanged'
     */
    public function handle(User $actor, string $targetId, string $roleId, string $reason, ?array $expectedAssignment): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi dan maksimal 2.000 karakter.']);
        }
        $result = DB::transaction(function () use ($actor, $targetId, $roleId, $reason, $expectedAssignment) {
            // Kunci user juga saat pivot belum ada; urutan tetap mencegah deadlock silang aktor/target.
            $users = User::whereIn('id', [$actor->id, $targetId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $target = $users->get($targetId) ?? User::whereKey($targetId)->firstOrFail();
            $currentActor = $users->get($actor->id) ?? User::whereKey($actor->id)->firstOrFail();
            $decisions = [
                'pengguna_read' => $this->permissions->decide($currentActor, 'pengguna:read'),
                'akses_update' => $this->permissions->decide($currentActor, 'akses:update'),
            ];
            $audit = ['actor_type' => 'user', 'actor_id' => $currentActor->id, 'sumber' => 'manual', 'objek_tipe' => 'users', 'objek_id' => $target->id, 'dasar_izin' => $decisions];
            if (! $decisions['pengguna_read']['allowed'] || ! $decisions['akses_update']['allowed']) {
                return ['denied' => true, 'audit' => $audit, 'message' => 'Anda tidak berwenang menetapkan peran.'];
            }
            $role = Str::isUuid($roleId) ? Role::whereKey($roleId)->sharedLock()->first() : null;
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
            if ($assignment && $assignment->role_id === $role->id) {
                return ['status' => 'unchanged'];
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

            return ['status' => $assignment ? 'changed' : 'assigned'];
        });
        if (isset($result['audit'])) {
            // Hanya Action memiliki audit denial; penolakan tidak hilang karena rollback mutasi.
            $this->audit->handle($result['audit'] + ['tindakan' => 'user_roles.ditolak', 'alasan' => $result['message']]);
            if (isset($result['denied'])) {
                throw new AuthorizationException($result['message']);
            }
            throw ValidationException::withMessages([$result['field'] => $result['message']]);
        }

        return $result['status'];
    }
}
