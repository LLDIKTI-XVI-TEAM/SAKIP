<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        /** @var User $actor */
        $actor = $request->user();
        if (! $actor || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
            abort(403, 'Hanya peran Admin dan Superadmin yang berwenang mencabut izin unit.');
        }

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan pencabutan izin wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan pencabutan izin minimal 5 karakter.',
        ]);

        $grant = UserPermissionGrant::with(['user.roles', 'permission', 'unit'])->findOrFail($id);

        // Admin tidak dapat merubah/mencabut izin dari Admin dan Superadmin
        if ($grant->user?->hasAnyRole(['admin', 'superadmin']) && ! $actor->hasRole('superadmin')) {
            abort(403, 'Admin tidak memiliki wewenang untuk mencabut izin unit dari pengguna dengan peran Admin atau Superadmin.');
        }

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
            actor: $actor,
            tindakan: 'user_permission_granted.hapus',
            objekTipe: 'user_permission_granted',
            objekId: (string) $grantId,
            nilaiLama: $oldValues,
            nilaiBaru: null,
            alasan: $validated['alasan'],
            dasarIzin: $permissionResolver->resolve($actor, 'akses:update')->toAuditBasis(),
        );

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$permName}' untuk pengguna {$userName} berhasil dicabut.");
    }
}
