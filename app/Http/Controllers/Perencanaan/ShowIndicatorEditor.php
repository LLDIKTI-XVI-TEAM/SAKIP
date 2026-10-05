<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\ReadIndicatorEditor;
use App\Http\Controllers\Controller;
use App\Models\IndikatorKinerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowIndicatorEditor extends Controller
{
    public function __invoke(Request $request, IndikatorKinerja $indikator, ReadIndicatorEditor $action): JsonResponse
    {
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'expected_updated_at' => ['sometimes', 'date']]);

        return response()->json($action->handle($request->user(), $indikator->id, true, (int) ($input['page'] ?? 1), $input['expected_updated_at'] ?? null));
    }
}
