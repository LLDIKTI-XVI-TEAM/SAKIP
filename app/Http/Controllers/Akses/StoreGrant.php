<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionCatalog;
use App\Services\PermissionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreGrant extends Controller
{
    public function __invoke(Request $request, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
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
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Anda tidak berwenang mengelola pemberian izin unit.',
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, 'Anda tidak berwenang mengelola pemberian izin unit.');
        }

        $rawAlasan = $request->input('alasan');
        if (is_string($rawAlasan)) {
            $request->merge(['alasan' => trim($rawAlasan)]);
        }

        $validated = $request->validate([
            'user_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'permission_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('permissions', 'id')->where(
                    fn ($query) => $query->where('aktif', true)
                        ->where('butuh_scope', Permission::SCOPE_UNIT)
                        ->whereIn('kode', PermissionCatalog::UNIT_SCOPED)
                ),
            ],
            'unit_id' => [
                'nullable',
                'bail',
                'uuid',
                Rule::exists('unit', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
            ],
            'alasan' => [
                'required',
                'string',
                'min:5',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '' || mb_strlen(trim((string) $value)) < 5) {
                        $fail('Alasan pemberian grant tidak boleh kosong atau hanya berisi spasi.');
                    }
                },
            ],
        ], [
            'user_id.required' => 'Pengguna target wajib dipilih.',
            'user_id.uuid' => 'Format ID pengguna tidak valid.',
            'user_id.exists' => 'Pengguna target tidak ditemukan atau berstatus nonaktif.',
            'permission_id.required' => 'Permission wajib dipilih.',
            'permission_id.uuid' => 'Format ID permission tidak valid.',
            'permission_id.exists' => 'Permission tidak ditemukan dalam katalog.',
            'unit_id.uuid' => 'Format ID unit tidak valid.',
            'unit_id.exists' => 'Unit target tidak ditemukan atau berstatus nonaktif.',
            'alasan.required' => 'Alasan pemberian grant wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pemberian grant minimal 5 karakter.',
        ]);

        /** @var User|null $targetUser */
        $targetUser = User::with('roles')->find($validated['user_id']);
        if (! $targetUser || ! $targetUser->is_active) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna target tidak ditemukan atau berstatus nonaktif.',
            ]);
        }

        // Admin tidak dapat merubah/memberikan izin kepada Admin dan Superadmin
        if ($targetUser->hasAnyRole(['admin', 'superadmin']) && ! $actor->hasRole('superadmin')) {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'user_permission_granted.ditolak',
                objekTipe: 'users',
                objekId: (string) $targetUser->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Admin tidak memiliki wewenang untuk memberikan izin unit kepada pengguna dengan peran Admin atau Superadmin.',
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, 'Admin tidak memiliki wewenang untuk memberikan izin unit kepada pengguna dengan peran Admin atau Superadmin.');
        }

        /** @var Permission $permission */
        $permission = Permission::find($validated['permission_id']);
        if (! $permission || ! $permission->aktif || ! in_array($permission->kode, PermissionCatalog::UNIT_SCOPED, true)) {
            throw ValidationException::withMessages([
                'permission_id' => 'Permission tidak ditemukan dalam katalog atau sudah dinonaktifkan.',
            ]);
        }

        // AC-2: Tolak jika permission bersifat global
        if ($permission->butuh_scope !== Permission::SCOPE_UNIT) {
            throw ValidationException::withMessages([
                'permission_id' => 'Hanya permission dengan cakupan unit (butuh_scope = unit) yang dapat diberikan melalui form ini.',
            ]);
        }

        // AC-3: Unit wajib untuk permission bertipe unit
        if (empty($validated['unit_id'])) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit target wajib dipilih untuk permission berscope unit.',
            ]);
        }

        // Pastikan unit_id valid dan aktif
        /** @var Unit|null $unit */
        $unit = Unit::find($validated['unit_id']);
        if (! $unit || $unit->status !== 'aktif') {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit target tidak ditemukan atau berstatus nonaktif.',
            ]);
        }

        // AC-4: Cek duplikasi user-permission-unit
        $isDuplicate = UserPermissionGrant::where('user_id', $validated['user_id'])
            ->where('permission_id', $validated['permission_id'])
            ->where('unit_id', $unit->id)
            ->exists();

        if ($isDuplicate) {
            throw ValidationException::withMessages([
                'permission_id' => 'Pengguna sudah memiliki izin tambahan untuk unit ini.',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($targetUser, $permission, $unit, $validated, $actor, $auditLogger, $permissionResolver) {
                // Kunci pengguna dengan urutan ID konsisten untuk menghindari deadlock
                $userIds = [$actor->id, $targetUser->id];
                sort($userIds);
                $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

                /** @var User $currentActor */
                $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

                /** @var User $lockedTargetUser */
                $lockedTargetUser = $lockedUsers->get($targetUser->id) ?? User::with('roles')->whereKey($targetUser->id)->sharedLock()->firstOrFail();

                // Otorisasi ulang aktor di dalam transaksi untuk mencegah race condition pencabutan hak akses
                $currentDecision = $permissionResolver->resolve($currentActor, 'akses:update');
                if (! $currentDecision->allowed || ! $currentActor->hasAnyRole(['admin', 'superadmin'])) {
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

                // Periksa hierarki: Admin tidak dapat memberikan izin kepada Admin atau Superadmin
                if ($lockedTargetUser->hasAnyRole(['admin', 'superadmin']) && ! $currentActor->hasRole('superadmin')) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'user_permission_granted.ditolak',
                        'objekTipe' => 'users',
                        'objekId' => (string) $lockedTargetUser->id,
                        'alasan' => 'Admin tidak memiliki wewenang untuk memberikan izin unit kepada pengguna dengan peran Admin atau Superadmin.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Admin tidak memiliki wewenang untuk memberikan izin unit kepada pengguna dengan peran Admin atau Superadmin.',
                    ];
                }

                // Validasi ulang status aktif pengguna target di dalam transaksi untuk mencegah TOCTOU
                if (! $lockedTargetUser->is_active) {
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
                if (! $lockedPermission || ! $lockedPermission->aktif || ! in_array($lockedPermission->kode, PermissionCatalog::UNIT_SCOPED, true)) {
                    throw ValidationException::withMessages([
                        'permission_id' => 'Permission tidak ditemukan dalam katalog atau sudah dinonaktifkan.',
                    ]);
                }

                if ($lockedPermission->butuh_scope !== Permission::SCOPE_UNIT) {
                    throw ValidationException::withMessages([
                        'permission_id' => 'Hanya permission dengan cakupan unit (butuh_scope = unit) yang dapat diberikan melalui form ini.',
                    ]);
                }

                // AC-1 & AC-5: Simpan grant
                $grant = UserPermissionGrant::create([
                    'user_id' => $lockedTargetUser->id,
                    'permission_id' => $lockedPermission->id,
                    'unit_id' => $lockedUnit->id,
                    'alasan' => $validated['alasan'],
                    'diberikan_oleh' => $currentActor->id,
                ]);

                // Audit Trail
                $auditLogger->catat(
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
            ->with('success', "Izin '{$result['permission_kode']}' pada unit '{$result['unit_nama']}' berhasil diberikan kepada {$result['target_nama']}.");
    }
}
