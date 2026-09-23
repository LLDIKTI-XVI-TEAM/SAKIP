<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevokeGrant extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if (! $actor) {
            abort(401);
        }

        $decision = $permissionResolver->resolve($actor, 'akses:update');
        if (! $decision->allowed || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'user_permission_granted.ditolak',
                objekTipe: 'user_permission_granted',
                objekId: $id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Anda tidak berwenang mengelola pencabutan izin unit.',
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, 'Anda tidak berwenang mengelola pencabutan izin unit.');
        }

        $rawAlasan = $request->input('alasan');
        if (is_string($rawAlasan)) {
            $request->merge(['alasan' => trim($rawAlasan)]);
        }

        $validated = $request->validate([
            'alasan' => [
                'required',
                'string',
                'min:5',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '' || mb_strlen(trim((string) $value)) < 5) {
                        $fail('Alasan pencabutan izin tidak boleh kosong atau hanya berisi spasi.');
                    }
                },
            ],
        ], [
            'alasan.required' => 'Alasan pencabutan izin wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pencabutan izin minimal 5 karakter.',
        ]);

        $result = DB::transaction(function () use ($id, $actor, $validated, $auditLogger, $permissionResolver) {
            /** @var UserPermissionGrant $grant */
            $grant = UserPermissionGrant::with(['permission', 'unit'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            // Kunci aktor dan pengguna target dengan urutan ID konsisten untuk menghindari deadlock
            $userIds = [$actor->id, $grant->user_id];
            sort($userIds);
            $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

            /** @var User $currentActor */
            $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

            /** @var User $lockedTargetUser */
            $lockedTargetUser = $lockedUsers->get($grant->user_id) ?? User::with('roles')->whereKey($grant->user_id)->sharedLock()->firstOrFail();

            // Kunci role aktif sumber aktor dengan sharedLock (mengikuti hierarki User -> Role -> Permission)
            // berurutan ID untuk mencegah race condition pencabutan wewenang role oleh ChangeRolePermission
            $actorRoleIds = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $currentActor->id)
                ->where('roles.aktif', true)
                ->pluck('roles.id')
                ->all();
            sort($actorRoleIds);
            if (! empty($actorRoleIds)) {
                Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
            }

            $currentDecision = $permissionResolver->resolve($currentActor, 'akses:update');
            if (! $currentDecision->allowed || ! $currentActor->hasAnyRole(['admin', 'superadmin'])) {
                return [
                    'status' => 'denied',
                    'actor' => $currentActor,
                    'tindakan' => 'user_permission_granted.ditolak',
                    'objekTipe' => 'user_permission_granted',
                    'objekId' => $id,
                    'alasan' => 'Anda tidak berwenang mengelola pencabutan izin unit.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                    'message' => 'Anda tidak berwenang mengelola pencabutan izin unit.',
                ];
            }

            // Temuan 1: Tolak pencabutan grant global melalui endpoint unit
            if ($grant->permission?->butuh_scope !== Permission::SCOPE_UNIT || $grant->unit_id === null) {
                abort(422, 'Endpoint ini hanya dapat mencabut grant yang berscope unit.');
            }

            // Admin tidak dapat merubah/mencabut izin dari Admin dan Superadmin
            if ($lockedTargetUser->hasAnyRole(['admin', 'superadmin']) && ! $currentActor->hasRole('superadmin')) {
                return [
                    'status' => 'denied',
                    'actor' => $currentActor,
                    'tindakan' => 'user_permission_granted.ditolak',
                    'objekTipe' => 'user_permission_granted',
                    'objekId' => (string) $grant->id,
                    'alasan' => 'Admin tidak memiliki wewenang untuk mencabut izin unit dari pengguna dengan peran Admin atau Superadmin.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                    'message' => 'Admin tidak memiliki wewenang untuk mencabut izin unit dari pengguna dengan peran Admin atau Superadmin.',
                ];
            }

            $oldValues = [
                'id' => $grant->id,
                'user_id' => $grant->user_id,
                'user_nama' => $lockedTargetUser->nama,
                'permission_id' => $grant->permission_id,
                'permission_kode' => $grant->permission?->kode,
                'unit_id' => $grant->unit_id,
                'unit_nama' => $grant->unit?->nama,
                'alasan_pemberian' => $grant->alasan,
                'diberikan_oleh' => $grant->diberikan_oleh,
            ];

            $grantId = $grant->id;
            $userName = $lockedTargetUser->nama;
            $permName = $grant->permission?->kode ?? 'Izin';

            $grant->delete();

            // AC-6: Audit Trail Pencabutan Izin
            $auditLogger->catat(
                actor: $currentActor,
                tindakan: 'user_permission_granted.hapus',
                objekTipe: 'user_permission_granted',
                objekId: (string) $grantId,
                nilaiLama: $oldValues,
                nilaiBaru: null,
                alasan: $validated['alasan'],
                dasarIzin: $currentDecision->toAuditBasis(),
            );

            return [
                'status' => 'revoked',
                'permName' => $permName,
                'userName' => $userName,
            ];
        });

        if (is_array($result) && ($result['status'] ?? null) === 'denied') {
            $auditLogger->catat(
                actor: $result['actor'],
                tindakan: $result['tindakan'],
                objekTipe: $result['objekTipe'],
                objekId: $result['objekId'],
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'],
                dasarIzin: $result['dasarIzin'],
            );

            abort(403, $result['message']);
        }

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$result['permName']}' untuk pengguna {$result['userName']} berhasil dicabut.");
    }
}
