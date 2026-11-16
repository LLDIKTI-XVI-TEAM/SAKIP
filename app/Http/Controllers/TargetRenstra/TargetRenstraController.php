<?php

namespace App\Http\Controllers\TargetRenstra;

use App\Actions\TargetRenstra\BacaRiwayatTargetRenstra;
use App\Actions\TargetRenstra\SimpanTargetRenstra;
use App\Http\Controllers\Controller;
use App\Http\Requests\TargetRenstra\SimpanTargetRenstraRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TargetRenstraController extends Controller
{
    public function riwayat(Request $request, string $indikator, BacaRiwayatTargetRenstra $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $indikator));
    }

    public function update(SimpanTargetRenstraRequest $request, string $indikator, SimpanTargetRenstra $action): RedirectResponse
    {
        $outcome = $action->handle($request->user(), $indikator, $request->validated());
        Inertia::flash('target_renstra', $outcome);

        return redirect()->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $outcome['renstra_id']]);
    }
}
