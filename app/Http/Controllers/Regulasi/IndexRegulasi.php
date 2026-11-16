<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\IndexRegulasi as IndexRegulasiAction;
use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexRegulasi extends Controller
{
    public function __invoke(Request $request, IndexRegulasiAction $action): Response
    {
        Gate::authorize('viewAny', Regulasi::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        return Inertia::render('Regulasi/Index', $action->handle(trim((string) ($filters['q'] ?? '')), $filters['status'] ?? null));
    }
}
