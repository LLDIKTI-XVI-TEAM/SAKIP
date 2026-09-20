<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\UserPermissionGranted;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeGrant extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('akses:update');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan pencabutan izin wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pencabutan izin minimal 5 karakter.',
        ]);

        $grant = UserPermissionGranted::with(['user', 'permission', 'unitKerja'])->findOrFail($id);

        $oldValues = [
            'id' => $grant->id,
            'user_id' => $grant->user_id,
            'user_name' => $grant->user->name,
            'permission_id' => $grant->permission_id,
            'permission_kode' => $grant->permission->kode ?? $grant->permission->name,
            'unit_id' => $grant->unit_id,
            'unit_kode' => $grant->unitKerja?->kode,
            'alasan_pemberian' => $grant->alasan,
            'diberikan_oleh' => $grant->diberikan_oleh,
        ];

        $grantId = $grant->id;
        $userName = $grant->user->name;
        $permName = $grant->permission->kode ?? $grant->permission->name;

        $grant->delete();

        // AC-6: Audit Trail Pencabutan Izin
        AuditLogger::catat(
            tindakan: 'user_permission_granted.hapus',
            objekTipe: 'user_permission_granted',
            objekId: $grantId,
            nilaiLama: $oldValues,
            nilaiBaru: null,
            alasan: $validated['alasan'],
            dasarIzin: [
                'permission' => 'akses:update',
                'roles' => $request->user()->getRoleNames()->all(),
            ]
        );

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permName}' untuk pengguna {$userName} berhasil dicabut.");
    }
}
