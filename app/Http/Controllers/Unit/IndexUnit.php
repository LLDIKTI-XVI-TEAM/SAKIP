<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexUnit extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Unit::class);

        $user = $request->user();
        $resolver = app(PermissionResolver::class);

        $units = Unit::withCount(['indikators', 'rencanaAksis', 'kegiatans', 'permissionGrants'])
            ->orderBy('nama')
            ->get()
            ->map(function (Unit $unit) use ($user, $resolver) {
                return [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                    'is_active' => $unit->status === 'aktif',
                    'indikators_count' => $unit->indikators_count,
                    'rencana_aksis_count' => $unit->rencana_aksis_count,
                    'kegiatans_count' => $unit->kegiatans_count,
                    'grants_count' => $unit->permission_grants_count,
                    'is_deletable' => $unit->isDeletable(),
                    'can' => [
                        'update' => $resolver->allows($user, 'unit:update'),
                        'delete' => $user->hasRole('superadmin') && $resolver->allows($user, 'unit:delete') && $unit->isDeletable(),
                    ],
                ];
            });

        return Inertia::render('Unit/Index', [
            'units' => $units,
            'can' => [
                'create' => $resolver->allows($user, 'unit:create'),
            ],
        ]);
    }
}
