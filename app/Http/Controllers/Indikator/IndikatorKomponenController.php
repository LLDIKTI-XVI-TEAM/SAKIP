<?php

namespace App\Http\Controllers\Indikator;

use App\Actions\Perencanaan\ReadIndicatorEditor;
use App\Http\Controllers\Controller;
use App\Models\IndikatorKinerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndikatorKomponenController extends Controller
{
    public function index(Request $request, IndikatorKinerja $indikator, ReadIndicatorEditor $action): Response|JsonResponse
    {
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'expected_updated_at' => ['sometimes', 'date']]);
        $editor = $action->handle($request->user(), $indikator->id, false, (int) ($input['page'] ?? 1), $input['expected_updated_at'] ?? null);

        return $request->expectsJson() ? response()->json($editor) : Inertia::render('Indikator/Komponen/Index', ['editor' => $editor]);
    }
}
