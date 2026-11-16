<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\PreviewTargetPeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\PreviewTargetPeriodeRequest;
use Illuminate\Http\JsonResponse;

class PreviewRencanaAksiTarget extends Controller
{
    /**
     * Pratinjau server-side tanpa persistensi: kalkulasi memakai
     * `CalculatePengukuran` yang sama dengan jalur baca/tulis.
     */
    public function __invoke(PreviewTargetPeriodeRequest $request, string $rencanaAksi, PreviewTargetPeriode $preview): JsonResponse
    {
        return response()->json($preview->handle($request->user(), $rencanaAksi, $request->validated()))->header('Cache-Control', 'no-store');
    }
}
