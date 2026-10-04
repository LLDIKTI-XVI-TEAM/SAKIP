<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, IndexRencanaAksi $index): Response
    {
        $header = RencanaAksi::findOrFail($rencanaAksi);
        Gate::authorize('view', $header);

        return Inertia::render('RencanaAksi/Show', ['rencanaAksi' => $index->handle($request->user(), $header)]);
    }
}
