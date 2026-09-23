<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
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
            /** @var User $currentActor */
            $currentActor = User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

            $currentDecision = $permissionResolver->resolve($currentActor, 'akses:update');
            if (! $currentDecision->allowed || ! $currentActor->hasAnyRole(['admin', 'superadmin'])) {
                $auditLogger->catat(
                    actor: $currentActor,
                    tindakan: 'user_permission_granted.ditolak',
                    objekTipe: 'user_permission_granted',
                    objekId: $id,
                    nilaiLama: null,
                    nilaiBaru: null,
                    alasan: 'Anda tidak berwenang mengelola pencabutan izin unit.',
                    dasarIzin: $currentDecision->toAuditBasis(),
                );

                abort(403, 'Anda tidak berwenang mengelola pencabutan izin unit.');
            }

            /** @var UserPermissionGrant $grant */
            $grant = UserPermissionGrant::with(['user.roles', 'permission', 'unit'])
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            // Temuan 1: Tolak pencabutan grant global melalui endpoint unit
            if ($grant->permission?->butuh_scope !== Permission::SCOPE_UNIT || $grant->unit_id === null) {
                abort(422, 'Endpoint ini hanya dapat mencabut grant yang berscope unit.');
            }

            // Admin tidak dapat merubah/mencabut izin dari Admin dan Superadmin
            if ($grant->user?->hasAnyRole(['admin', 'superadmin']) && ! $currentActor->hasRole('superadmin')) {
                return [
                    'status' => 'denied',
                    'grant_id' => $grant->id,
                    'message' => 'Admin tidak memiliki wewenang untuk mencabut izin unit dari pengguna dengan peran Admin atau Superadmin.',
                ];
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

        if ($result['status'] === 'denied') {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'user_permission_granted.ditolak',
                objekTipe: 'user_permission_granted',
                objekId: (string) $result['grant_id'],
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['message'],
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, $result['message']);
        }

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$result['permName']}' untuk pengguna {$result['userName']} berhasil dicabut.");
    }
}
