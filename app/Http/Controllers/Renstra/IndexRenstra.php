<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\IndexRenstra as IndexRenstraAction;
use App\Http\Controllers\Controller;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IndexRenstra extends Controller
{
    public function __invoke(Request $request, IndexRenstraAction $action): Response
    {
        Gate::authorize('viewAny', Renstra::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                Renstra::STATUS_DRAFT,
                Renstra::STATUS_AKTIF,
                Renstra::STATUS_NONAKTIF,
                Renstra::STATUS_DIARSIPKAN,
            ])],
        ]);

        return Inertia::render('Renstra/Index', $action->handle($request->user(), trim((string) ($filters['q'] ?? '')), $filters['status'] ?? null));
    }
}
