<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionCatalog;
use App\Services\PermissionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUnitGrantAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * @param  array{user_id:string,permission_id:string,unit_id?:string|null,alasan:string}  $data
     * @return array{permission_kode:string,unit_nama:string,target_nama:string}
     */
    public function handle(User $actor, array $data): array
    {
        /** @var User|null $targetUser */
        $targetUser = User::with('roles')->find($data['user_id']);
        if (! $targetUser || $targetUser->status !== 'aktif') {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna target tidak ditemukan atau berstatus nonaktif.',
            ]);
        }

        /** @var Permission $permission */
        $permission = Permission::find($data['permission_id']);
        if (! $permission || ! $permission->aktif || ! in_array($permission->kode, PermissionCatalog::UNIT_SCOPED, true)) {
            throw ValidationException::withMessages([
                'permission_id' => 'Permission tidak ditemukan dalam katalog atau sudah dinonaktifkan.',
            ]);
        }

        // Grant Unit tidak memberikan permission global.
        if ($permission->butuh_scope !== Permission::SCOPE_UNIT) {
            throw ValidationException::withMessages([
                'permission_id' => 'Hanya permission dengan cakupan unit (butuh_scope = unit) yang dapat diberikan melalui form ini.',
            ]);
        }

        // Permission unit wajib menyertakan unit target.
        if (empty($data['unit_id'])) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit target wajib dipilih untuk permission berscope unit.',
            ]);
        }

        // Pastikan unit_id valid dan aktif
        /** @var Unit|null $unit */
        $unit = Unit::find($data['unit_id']);
        if (! $unit || $unit->status !== 'aktif') {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit target tidak ditemukan atau berstatus nonaktif.',
            ]);
        }

        // Pemeriksaan awal duplikasi; indeks unik tetap menjadi penjaga terakhir.
        $isDuplicate = UserPermissionGrant::where('user_id', $data['user_id'])
            ->where('permission_id', $data['permission_id'])
            ->where('unit_id', $unit->id)
            ->exists();

        if ($isDuplicate) {
            throw ValidationException::withMessages([
                'permission_id' => 'Pengguna sudah memiliki izin tambahan untuk unit ini.',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($targetUser, $permission, $unit, $data, $actor) {
                // Kunci pengguna dengan urutan ID konsisten untuk menghindari deadlock
                $userIds = [$actor->id, $targetUser->id];
                sort($userIds);
                $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

                /** @var User $currentActor */
                $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

                /** @var User $lockedTargetUser */
                $lockedTargetUser = $lockedUsers->get($targetUser->id) ?? User::with('roles')->whereKey($targetUser->id)->sharedLock()->firstOrFail();

                // Kunci role sumber terurut agar perubahan preset tidak menyela evaluasi izin.
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

                // Otorisasi ulang aktor di dalam transaksi untuk mencegah race condition pencabutan hak akses
                $currentDecision = $this->permissionResolver->resolve($currentActor, 'delegasi:update');
                if (! $currentDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'user_permission_granted.ditolak',
                        'objekTipe' => 'user_permission_granted',
                        'objekId' => (string) Str::uuid(),
                        'alasan' => 'Anda tidak berwenang mengelola pemberian izin unit.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Anda tidak berwenang mengelola pemberian izin unit.',
                    ];
                }

                // Validasi ulang status aktif pengguna target di dalam transaksi untuk mencegah TOCTOU
                if ($lockedTargetUser->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'user_id' => 'Pengguna target tidak ditemukan atau berstatus nonaktif.',
                    ]);
                }

                // Kunci unit dengan sharedLock agar urutan penguncian konsisten terhadap lockForUpdate di penghapusan unit,
                // dan validasi ulang status aktif unit di dalam transaksi untuk mencegah TOCTOU
                /** @var Unit $lockedUnit */
                $lockedUnit = Unit::whereKey($unit->id)->sharedLock()->firstOrFail();
                if ($lockedUnit->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Unit target tidak ditemukan atau berstatus nonaktif.',
                    ]);
                }

                // Kunci permission dengan sharedLock dan validasi ulang status aktif serta butuh_scope di dalam transaksi
                /** @var Permission|null $lockedPermission */
                $lockedPermission = Permission::whereKey($permission->id)->sharedLock()->first();
                if (! $lockedPermission || ! $lockedPermission->aktif || ! in_array($lockedPermission->kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true)) {
                    throw ValidationException::withMessages([
                        'permission_id' => 'Permission tidak ditemukan dalam katalog atau sudah dinonaktifkan.',
                    ]);
                }

                if ($lockedPermission->butuh_scope !== Permission::SCOPE_UNIT) {
                    throw ValidationException::withMessages([
                        'permission_id' => 'Hanya permission dengan cakupan unit (butuh_scope = unit) yang dapat diberikan melalui form ini.',
                    ]);
                }

                $grant = UserPermissionGrant::create([
                    'user_id' => $lockedTargetUser->id,
                    'permission_id' => $lockedPermission->id,
                    'unit_id' => $lockedUnit->id,
                    'alasan' => $data['alasan'],
                    'diberikan_oleh' => $currentActor->id,
                ]);

                $this->auditLogger->catat(
                    actor: $currentActor,
                    tindakan: 'user_permission_granted.tambah',
                    objekTipe: 'user_permission_granted',
                    objekId: (string) $grant->id,
                    nilaiLama: null,
                    nilaiBaru: [
                        'user_id' => $lockedTargetUser->id,
                        'user_nama' => $lockedTargetUser->nama,
                        'permission_id' => $lockedPermission->id,
                        'permission_kode' => $lockedPermission->kode,
                        'unit_id' => $lockedUnit->id,
                        'unit_nama' => $lockedUnit->nama,
                        'alasan' => $grant->alasan,
                        'diberikan_oleh' => $currentActor->id,
                    ],
                    alasan: $grant->alasan,
                    dasarIzin: $currentDecision->toAuditBasis(),
                );

                return [
                    'status' => 'created',
                    'permission_kode' => $lockedPermission->kode,
                    'unit_nama' => $lockedUnit->nama,
                    'target_nama' => $lockedTargetUser->nama,
                ];
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'permission_id' => 'Permission tidak ditemukan, sudah dinonaktifkan, atau tidak memenuhi syarat grant.',
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->errorInfo[2] ?? '', 'user_permission_granted')) {
                throw ValidationException::withMessages([
                    'permission_id' => 'Pengguna sudah memiliki izin tambahan untuk unit ini.',
                ]);
            }
            throw $exception;
        }

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

        return [
            'permission_kode' => $result['permission_kode'],
            'unit_nama' => $result['unit_nama'],
            'target_nama' => $result['target_nama'],
        ];
    }
}
