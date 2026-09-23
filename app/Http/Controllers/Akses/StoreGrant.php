<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
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

        $validated = $request->validate([
            'user_id' => [
                'required',
                'bail',
                'uuid',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'permission_id' => ['required', 'bail', 'uuid', 'exists:permissions,id'],
            'unit_id' => [
                'nullable',
                'bail',
                'uuid',
                Rule::exists('unit', 'id')->where(fn ($query) => $query->where('status', 'aktif')),
            ],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
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
        if (! $permission || ! $permission->aktif) {
            throw ValidationException::withMessages([
                'permission_id' => 'Permission tidak ditemukan atau sudah dinonaktifkan.',
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
            DB::transaction(function () use ($targetUser, $permission, $unit, $validated, $actor, $auditLogger, $decision) {
                // Kunci dan validasi ulang target user di dalam transaksi untuk mencegah TOCTOU
                /** @var User $lockedUser */
                $lockedUser = User::whereKey($targetUser->id)->sharedLock()->firstOrFail();
                if (! $lockedUser->is_active) {
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

                // AC-1 & AC-5: Simpan grant
                $grant = UserPermissionGrant::create([
                    'user_id' => $lockedUser->id,
                    'permission_id' => $permission->id,
                    'unit_id' => $lockedUnit->id,
                    'alasan' => $validated['alasan'],
                    'diberikan_oleh' => $actor->id,
                ]);

                // Audit Trail
                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'user_permission_granted.tambah',
                    objekTipe: 'user_permission_granted',
                    objekId: (string) $grant->id,
                    nilaiLama: null,
                    nilaiBaru: [
                        'user_id' => $lockedUser->id,
                        'user_nama' => $lockedUser->nama,
                        'permission_id' => $permission->id,
                        'permission_kode' => $permission->kode,
                        'unit_id' => $lockedUnit->id,
                        'unit_nama' => $lockedUnit->nama,
                        'alasan' => $grant->alasan,
                        'diberikan_oleh' => $actor->id,
                    ],
                    alasan: $grant->alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->errorInfo[2] ?? '', 'user_permission_granted')) {
                throw ValidationException::withMessages([
                    'permission_id' => 'Pengguna sudah memiliki izin tambahan untuk unit ini.',
                ]);
            }
            throw $exception;
        }

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permission->kode}' pada unit '{$unit->nama}' berhasil diberikan kepada {$targetUser->nama}.");
    }
}
