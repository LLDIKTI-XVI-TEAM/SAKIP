<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\DaftarReviewRencanaAksi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexReviewRencanaAksi extends Controller
{
    public function __invoke(Request $request, DaftarReviewRencanaAksi $action): Response
    {
        return Inertia::render('RencanaAksi/ReviewIndex', $action->handle($request->user(), $request->query()));
    }
}
