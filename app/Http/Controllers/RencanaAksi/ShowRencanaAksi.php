<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PresentRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $id, PresentRencanaAksi $present): Response
    {
        $rencanaAksi = RencanaAksi::with('jadwalSnapshot')->findOrFail($id);
        Gate::authorize('view', $rencanaAksi);
        $actor = $request->user();

        return Inertia::render('RencanaAksi/Show', ['rencanaAksi' => $present->handle($rencanaAksi, $actor, true)]);
    }
}
