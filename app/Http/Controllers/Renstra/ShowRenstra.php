<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra): Response
    {
        Gate::authorize('view', $renstra);

        $renstra->load([
            'regulasi',
            'pembuat',
            'berkas' => fn ($query) => $query->with('pengunggah')->orderByDesc('created_at'),
            'sasaranStrategis' => fn ($query) => $query->with('indikatorKinerjas')->orderBy('urutan'),
        ]);

        $user = $request->user();

        return Inertia::render('Renstra/Show', [
            'renstra' => $renstra,
            'can' => [
                'update' => $user->can('update', $renstra),
                'delete' => $user->can('delete', $renstra),
                'deleteAttachment' => $user->can('delete', $renstra),
            ],
        ]);
    }
}
