<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\DaftarRencanaAksi as DaftarRencanaAksiAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DaftarRencanaAksi extends Controller
{
    public function __invoke(Request $request, DaftarRencanaAksiAction $action): Response
    {
        return Inertia::render('RencanaAksi/Index', $action->handle($request->user(), $request->query()));
    }
}
