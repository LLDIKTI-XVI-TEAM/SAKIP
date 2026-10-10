<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\AntreanRencanaAksi;
use App\Actions\RencanaAksi\DaftarRencanaAksi as DaftarRencanaAksiAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $status = (string) $request->query('status', 'draf');
        if ($status === 'draf') {
            return Inertia::render('RencanaAksi/Index', $draf->handle($request->user(), $request->query()));
        }

        return Inertia::render('RencanaAksi/Antrean', $antrean->handle($request->user(), $request->query()));
    }
}
