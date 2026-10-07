<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PresentRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $id, PresentRencanaAksi $present, PermissionResolver $resolver): Response
    {
        $rencanaAksi = RencanaAksi::with('jadwalSnapshot')->findOrFail($id);
        Gate::authorize('view', $rencanaAksi);
        $actor = $request->user();
        abort_unless($resolver->allows($actor, 'rencana_aksi:sahkan', $rencanaAksi->targetUnitId()), 403);

        return Inertia::render('RencanaAksi/Show', ['rencanaAksi' => $present->handle($rencanaAksi, $actor, true)]);
    }
}
