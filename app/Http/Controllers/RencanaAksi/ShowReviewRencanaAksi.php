<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\BacaReviewRencanaAksi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowReviewRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, BacaReviewRencanaAksi $action): Response
    {
        return Inertia::render('RencanaAksi/Review', ['rencanaAksi' => $action->handle($request->user(), $rencanaAksi, $request->query())]);
    }
}
