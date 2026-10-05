<?php

namespace App\Http\Controllers\TargetTahunan;

use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Http\Controllers\Controller;
use App\Http\Requests\TargetTahunan\SaveTargetTahunanRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TargetTahunanController extends Controller
{
    public function editor(Request $request, string $indikator, int $tahun, ShowTargetTahunan $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $indikator, $tahun));
    }

    public function update(SaveTargetTahunanRequest $request, string $indikator, int $tahun, SaveTargetTahunan $action): JsonResponse|RedirectResponse
    {
        $outcome = $action->handle($request->user(), $indikator, $tahun, $request->validated());
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json($outcome);
        }
        Inertia::flash('target_tahunan', $outcome);

        // Pertahankan filter halaman asal; jangan menerima URL redirect atau query bebas.
        $origin = $request->headers->get('referer', '');
        $index = route('perencanaan.sasaran-indikator.index');
        $query = [];
        if (strtok($origin, '?') === $index) {
            parse_str((string) parse_url($origin, PHP_URL_QUERY), $parameters);
            if (isset($parameters['renstra_id']) && is_string($parameters['renstra_id']) && Str::isUuid($parameters['renstra_id'])) {
                $query['renstra_id'] = $parameters['renstra_id'];
            }
        }

        return redirect()->route('perencanaan.sasaran-indikator.index', $query);
    }
}
