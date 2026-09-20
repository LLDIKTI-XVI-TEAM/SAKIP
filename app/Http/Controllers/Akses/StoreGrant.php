<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UserPermissionGranted;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreGrant extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('akses:update');

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'permission_id' => ['required', 'exists:permissions,id'],
            'unit_id' => ['nullable'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'user_id.required' => 'Pengguna target wajib dipilih.',
            'user_id.exists' => 'Pengguna target tidak ditemukan.',
            'permission_id.required' => 'Permission wajib dipilih.',
            'permission_id.exists' => 'Permission tidak ditemukan dalam katalog.',
            'alasan.required' => 'Alasan pemberian grant wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pemberian grant minimal 5 karakter.',
        ]);

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
        $unit = UnitKerja::findOrFail($validated['unit_id']);

        // AC-4: Cek duplikasi user-permission-unit
        $isDuplicate = UserPermissionGranted::where('user_id', $validated['user_id'])
            ->where('permission_id', $validated['permission_id'])
            ->where('unit_id', $unit->id)
            ->exists();

        if ($isDuplicate) {
            throw ValidationException::withMessages([
                'permission_id' => 'Pengguna sudah memiliki izin tambahan untuk unit ini.',
            ]);
        }

        $targetUser = User::findOrFail($validated['user_id']);

        // AC-1 & AC-5: Simpan grant
        $grant = UserPermissionGranted::create([
            'user_id' => $targetUser->id,
            'permission_id' => $permission->id,
            'unit_id' => $unit->id,
            'alasan' => $validated['alasan'],
            'diberikan_oleh' => $request->user()->id,
        ]);

        // Audit Trail
        AuditLogger::catat(
            tindakan: 'user_permission_granted.tambah',
            objekTipe: 'user_permission_granted',
            objekId: $grant->id,
            nilaiLama: null,
            nilaiBaru: [
                'user_id' => $targetUser->id,
                'user_name' => $targetUser->name,
                'permission_id' => $permission->id,
                'permission_kode' => $permission->kode ?? $permission->name,
                'unit_id' => $unit->id,
                'unit_kode' => $unit->kode,
                'alasan' => $grant->alasan,
                'diberikan_oleh' => $request->user()->id,
            ],
            alasan: $grant->alasan,
            dasarIzin: [
                'permission' => 'akses:update',
                'roles' => $request->user()->getRoleNames()->all(),
            ]
        );

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permission->name}' pada unit '{$unit->nama}' berhasil diberikan kepada {$targetUser->name}.");
    }
}
