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

        $canCreate = $resolver->allows($user, 'unit:create');
        $canUpdate = $resolver->allows($user, 'unit:update');
        $canDeleteUnit = $user->hasRole('superadmin') && $resolver->allows($user, 'unit:delete');

        $units = Unit::withCount(['indikators', 'rencanaAksis', 'kegiatans', 'permissionGrants', 'permissionDenies'])
            ->orderBy('nama')
            ->get()
            ->map(function (Unit $unit) use ($canUpdate, $canDeleteUnit) {
                $isDeletable = $unit->isDeletable();

                return [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                    'is_active' => $unit->status === 'aktif',
                    'indikators_count' => $unit->indikators_count,
                    'rencana_aksis_count' => $unit->rencana_aksis_count,
                    'kegiatans_count' => $unit->kegiatans_count,
                    'grants_count' => $unit->permission_grants_count,
                    'denies_count' => $unit->permission_denies_count,
                    'is_deletable' => $isDeletable,
                    'can' => [
                        'update' => $canUpdate,
                        'delete' => $canDeleteUnit && $isDeletable,
                    ],
                ];
            });

        return Inertia::render('Unit/Index', [
            'units' => $units,
            'can' => [
                'create' => $canCreate,
            ],
        ]);
    }
}
