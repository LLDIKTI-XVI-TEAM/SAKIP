<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, IndexRencanaAksi $index): Response
    {
        return Inertia::render('RencanaAksi/Show', ['rencanaAksi' => $index->handle($request->user(), $rencanaAksi)]);
    }
}
