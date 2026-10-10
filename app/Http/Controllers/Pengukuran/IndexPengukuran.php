<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\IndexPengukuran as IndexPengukuranAction;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengukuran extends Controller
{
    public function __invoke(Request $request, IndexPengukuranAction $action): Response
    {
        Gate::authorize('viewAny', PengukuranKinerja::class);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('Pengukuran/Index', $action->handle($request->user()));
    }
}
