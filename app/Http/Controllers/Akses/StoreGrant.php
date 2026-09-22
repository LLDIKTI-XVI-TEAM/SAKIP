<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreGrant extends Controller
{
    public function __invoke(Request $request, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        Gate::authorize('akses:update');

        /** @var User $actor */
        $actor = $request->user();
        if (! $actor || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
            abort(403, 'Hanya peran Admin dan Superadmin yang berwenang memberikan izin unit.');
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permission_id' => ['required', 'exists:permissions,id'],
            'unit_id' => ['nullable', 'exists:unit,id'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'user_id.required' => 'Pengguna target wajib dipilih.',
            'user_id.exists' => 'Pengguna target tidak ditemukan.',
            'permission_id.required' => 'Permission wajib dipilih.',
            'permission_id.exists' => 'Permission tidak ditemukan dalam katalog.',
            'unit_id.exists' => 'Unit target tidak ditemukan.',
            'alasan.required' => 'Alasan pemberian grant wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pemberian grant minimal 5 karakter.',
        ]);

        /** @var User $targetUser */
        $targetUser = User::with('roles')->findOrFail($validated['user_id']);

        // Admin tidak dapat merubah/memberikan izin kepada Admin dan Superadmin
        if ($targetUser->hasAnyRole(['admin', 'superadmin']) && ! $actor->hasRole('superadmin')) {
            abort(403, 'Admin tidak memiliki wewenang untuk memberikan izin unit kepada pengguna dengan peran Admin atau Superadmin.');
        }

        /** @var Permission $permission */
        $permission = Permission::findOrFail($validated['permission_id']);

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
        /** @var Unit $unit */
        $unit = Unit::findOrFail($validated['unit_id']);

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

        // AC-1 & AC-5: Simpan grant
        $grant = UserPermissionGrant::create([
            'user_id' => $targetUser->id,
            'permission_id' => $permission->id,
            'unit_id' => $unit->id,
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
                'user_id' => $targetUser->id,
                'user_nama' => $targetUser->nama,
                'permission_id' => $permission->id,
                'permission_kode' => $permission->kode,
                'unit_id' => $unit->id,
                'unit_nama' => $unit->nama,
                'alasan' => $grant->alasan,
                'diberikan_oleh' => $actor->id,
            ],
            alasan: $grant->alasan,
            dasarIzin: $permissionResolver->resolve($actor, 'akses:update')->toAuditBasis(),
        );

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permission->kode}' pada unit '{$unit->nama}' berhasil diberikan kepada {$targetUser->nama}.");
    }
}
