<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RevokeGrant extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        Gate::authorize('akses:update');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan pencabutan izin wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pencabutan izin minimal 5 karakter.',
        ]);

        $grant = UserPermissionGrant::with(['user', 'permission', 'unit'])->findOrFail($id);

        $oldValues = [
            'id' => $grant->id,
            'user_id' => $grant->user_id,
            'user_nama' => $grant->user?->nama,
            'permission_id' => $grant->permission_id,
            'permission_kode' => $grant->permission?->kode,
            'unit_id' => $grant->unit_id,
            'unit_nama' => $grant->unit?->nama,
            'alasan_pemberian' => $grant->alasan,
            'diberikan_oleh' => $grant->diberikan_oleh,
        ];

        $grantId = $grant->id;
        $userName = $grant->user?->nama ?? 'Pengguna';
        $permName = $grant->permission?->kode ?? 'Izin';

        $grant->delete();

        // AC-6: Audit Trail Pencabutan Izin
        $auditLogger->catat(
            actor: $request->user(),
            tindakan: 'user_permission_granted.hapus',
            objekTipe: 'user_permission_granted',
            objekId: (string) $grantId,
            nilaiLama: $oldValues,
            nilaiBaru: null,
            alasan: $validated['alasan'],
            dasarIzin: $permissionResolver->resolve($request->user(), 'akses:update')->toAuditBasis(),
        );

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permName}' untuk pengguna {$userName} berhasil dicabut.");
    }
}
