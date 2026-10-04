<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\PreviewIndicatorFormula as PreviewAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\PreviewIndicatorFormulaRequest;
use App\Models\IndikatorKinerja;
use Illuminate\Http\JsonResponse;

class PreviewIndicatorFormula extends Controller
{
    public function __invoke(PreviewIndicatorFormulaRequest $request, IndikatorKinerja $indikator, PreviewAction $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $indikator->id, $request->validated()));
    }
}
