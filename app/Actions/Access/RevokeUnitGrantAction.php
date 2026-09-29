<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class RevokeUnitGrantAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Cabut Grant Unit dengan urutan lock pengguna terurut sebelum grant dan otorisasi ulang.
     * Referensi nonaktif tetap dapat dibersihkan; pencabutan dan audit sukses atomik.
     *
     * @return array{permName:string,userName:string}
     */
    public function handle(User $actor, string $id, string $alasan): array
    {
        $result = DB::transaction(function () use ($id, $actor, $alasan) {
            // Baca target tanpa lock, lalu ikuti urutan pengguna → grant agar tidak berlawanan dengan mutasi akun.
            $grantRow = UserPermissionGrant::whereKey($id)->firstOrFail();
            $userIds = array_values(array_unique([$actor->id, $grantRow->user_id]));
            sort($userIds);
            $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

            /** @var UserPermissionGrant $grant */
            $grant = UserPermissionGrant::with(['permission', 'unit'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var User $currentActor */
            $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

            /** @var User $lockedTargetUser */
            $lockedTargetUser = $lockedUsers->get($grant->user_id) ?? User::with('roles')->whereKey($grant->user_id)->sharedLock()->firstOrFail();

            // Kunci role sumber terurut agar perubahan preset tidak menyela evaluasi izin.
            $currentActor->lockActiveRoles();

            $currentDecision = $this->permissionResolver->resolve($currentActor, PermissionCodes::DELEGASI_UPDATE);
            if (! $currentDecision->allowed) {
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

            // Endpoint Unit tidak mencabut grant global; referensi nonaktif tetap boleh dibersihkan.
            if ($grant->permission?->butuh_scope !== Permission::SCOPE_UNIT || $grant->unit_id === null) {
                abort(422, 'Endpoint ini hanya dapat mencabut grant yang berscope unit.');
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

            $this->auditLogger->catat(
                actor: $currentActor,
                tindakan: 'user_permission_granted.hapus',
                objekTipe: 'user_permission_granted',
                objekId: (string) $grantId,
                nilaiLama: $oldValues,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $currentDecision->toAuditBasis(),
            );

            return [
                'status' => 'revoked',
                'permName' => $permName,
                'userName' => $userName,
            ];
        });

        if (is_array($result) && ($result['status'] ?? null) === 'denied') {
            $this->auditLogger->catat(
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

        return ['permName' => $result['permName'], 'userName' => $result['userName']];
    }
}
