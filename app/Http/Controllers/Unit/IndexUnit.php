<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexUnit extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', UnitKerja::class);

        $user = $request->user();

        $units = UnitKerja::with('parent')
            ->withCount(['penugasanIndikators', 'users', 'children'])
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get()
            ->map(function (UnitKerja $unit) use ($user) {
                return [
                    'id' => $unit->id,
                    'kode' => $unit->kode,
                    'nama' => $unit->nama,
                    'singkatan' => $unit->singkatan,
                    'parent_id' => $unit->parent_id,
                    'parent_nama' => $unit->parent?->nama,
                    'urutan' => $unit->urutan,
                    'is_active' => $unit->is_active,
                    'penugasan_count' => $unit->penugasan_indicators_count ?? $unit->penugasanIndikators()->count(),
                    'users_count' => $unit->users_count ?? $unit->users()->count(),
                    'children_count' => $unit->children_count ?? $unit->children()->count(),
                    'is_deletable' => $unit->isDeletable(),
                    'can' => [
                        'update' => $user->can('update', $unit),
                        'delete' => $user->hasRole('superadmin') && $unit->isDeletable(),
                    ],
                ];
            });

        $parentOptions = UnitKerja::where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan']);

        return Inertia::render('Unit/Index', [
            'units' => $units,
            'parentOptions' => $parentOptions,
            'can' => [
                'create' => $user->can('create', UnitKerja::class),
            ],
        ]);
    }
}
