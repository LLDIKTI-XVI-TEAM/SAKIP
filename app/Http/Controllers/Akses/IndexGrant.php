<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Authorization\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IndexGrant extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('akses:update');

        /** @var User $actor */
        $actor = $request->user();
        if (! $actor || ! $actor->hasAnyRole(['admin', 'superadmin'])) {
            abort(403, 'Hanya peran Admin dan Superadmin yang berwenang mengakses manajemen izin unit.');
        }

        $actorIsSuperadmin = $actor->hasRole('superadmin');

        $search = trim($request->string('search')->toString());
        $unitId = $request->query('unit_id');

        $grantsQuery = UserPermissionGrant::with([
            'user:id,nama,email',
            'user.roles:id,nama,kode,aktif',
            'permission:id,kode,keterangan,butuh_scope',
            'unit:id,nama',
            'diberikanOleh:id,nama',
        ])
            ->whereNotNull('unit_id')
            ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT));

        if ($search !== '') {
            $grantsQuery->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('permission', function ($pq) use ($search) {
                        $pq->where('kode', 'ilike', "%{$search}%")
                            ->orWhere('keterangan', 'ilike', "%{$search}%");
                    })
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('nama', 'ilike', "%{$search}%");
                    });
            });
        }

        if (! empty($unitId) && $unitId !== 'all') {
            if (is_string($unitId) && Str::isUuid($unitId) && Unit::where('id', $unitId)->where('status', 'aktif')->exists()) {
                $grantsQuery->where('unit_id', $unitId);
            } else {
                $unitId = 'all';
            }
        }

        $grants = $grantsQuery->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (UserPermissionGrant $grant) {
                return [
                    'id' => $grant->id,
                    'user_id' => $grant->user_id,
                    'user_name' => $grant->user?->nama ?? '-',
                    'user_email' => $grant->user?->email ?? '-',
                    'user_roles' => $grant->user?->roles->pluck('nama')->all() ?? [],
                    'permission_id' => $grant->permission_id,
                    'permission_kode' => $grant->permission?->kode,
                    'permission_keterangan' => $grant->permission?->keterangan,
                    'unit_id' => $grant->unit_id,
                    'unit_nama' => $grant->unit?->nama,
                    'alasan' => $grant->alasan,
                    'diberikan_oleh_nama' => $grant->diberikanOleh?->nama ?? '-',
                    'created_at' => $grant->created_at?->toISOString(),
                    'can_revoke' => true,
                ];
            });

        $units = Unit::where('status', 'aktif')
            ->select('id', 'nama')
            ->orderBy('nama')
            ->get();

        $unitPermissions = Permission::where('butuh_scope', Permission::SCOPE_UNIT)
            ->where('aktif', true)
            ->whereIn('kode', PermissionCatalog::UNIT_SCOPED)
            ->select('id', 'kode', 'entitas', 'aksi', 'keterangan')
            ->orderBy('kode')
            ->get()
            ->map(fn (Permission $p) => [
                'id' => $p->id,
                'name' => $p->kode,
                'kode' => $p->kode,
                'keterangan' => $p->keterangan,
            ]);

        return Inertia::render('Akses/GrantIndex', [
            'grants' => $grants,
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId ?? 'all',
            ],
            'users' => [],
            'units' => $units,
            'unitPermissions' => $unitPermissions,
            'is_superadmin' => $actorIsSuperadmin,
            'can' => [
                'create_grant' => true,
                'revoke_grant' => true,
            ],
        ]);
    }
}
