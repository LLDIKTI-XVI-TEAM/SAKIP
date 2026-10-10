<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\IndexBuktiRencanaAksi;
use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, IndexRencanaAksi $index, IndexBuktiRencanaAksi $bukti): Response
    {
        // Payload utama dievaluasi lebih dulu sehingga 404/403 header tetap dijawab IndexRencanaAksi.
        return Inertia::render('RencanaAksi/Show', [
            'rencanaAksi' => $index->handle($request->user(), $rencanaAksi),
            'bukti' => $bukti->handle($request->user(), $rencanaAksi),
        ]);
    }
}
