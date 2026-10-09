<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\AntreanRencanaAksi;
use App\Actions\RencanaAksi\DaftarRencanaAksi as DaftarRencanaAksiAction;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DaftarRencanaAksi extends Controller
{
    /**
     * Daftar Rencana Aksi bertab: titik masuk penyusunan (default) serta
     * antrean pengesahan dan daftar disahkan (ISS-05.05).
     */
    public function __invoke(Request $request, DaftarRencanaAksiAction $draf, AntreanRencanaAksi $antrean): Response
    {
        Gate::authorize('viewAny', RencanaAksi::class);
        $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:draf,antrean,disahkan'],
        ]);
        $status = (string) $request->query('status', 'draf');
        if ($status === 'draf') {
            return Inertia::render('RencanaAksi/Index', $draf->handle($request->user()));
        }

        return Inertia::render('RencanaAksi/Antrean', $antrean->handle($request->user(), $status));
    }
}
