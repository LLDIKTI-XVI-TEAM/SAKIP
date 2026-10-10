<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PresentRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowReviuRencanaAksi extends Controller
{
    /** Layar reviu pengesahan: konteks beku dari snapshot versi pengajuan terbaru. */
    public function __invoke(Request $request, string $id, PresentRencanaAksi $present): Response
    {
        $rencanaAksi = RencanaAksi::with('latestVersion.jadwalSnapshot')->findOrFail($id);
        Gate::authorize('view', $rencanaAksi);

        return Inertia::render('RencanaAksi/Reviu', ['rencanaAksi' => $present->handle($rencanaAksi, $request->user(), true)]);
    }
}
