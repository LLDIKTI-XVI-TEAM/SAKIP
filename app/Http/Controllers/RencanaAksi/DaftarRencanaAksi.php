<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\DaftarRencanaAksi as DaftarRencanaAksiAction;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DaftarRencanaAksi extends Controller
{
    public function __invoke(Request $request, DaftarRencanaAksiAction $action): Response
    {
        Gate::authorize('viewAny', RencanaAksi::class);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('RencanaAksi/Index', $action->handle($request->user()));
    }
}
