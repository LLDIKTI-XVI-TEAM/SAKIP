<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\AntreanRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexRencanaAksi extends Controller
{
    public function __invoke(Request $request, AntreanRencanaAksi $action): Response
    {
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:antrean,disahkan'],
        ]);
        // Akses lihat longgar: cukup rencana_aksi:read; deny unit disaring per baris di Action.
        Gate::authorize('viewAny', RencanaAksi::class);

        return Inertia::render('RencanaAksi/Index', $action->handle($request->user(), (string) $request->query('status', 'antrean')));
    }
}
