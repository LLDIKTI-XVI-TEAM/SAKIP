<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\ChangeIndicatorFormula as ChangeIndicatorFormulaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\ChangeIndicatorFormulaRequest;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ChangeIndicatorFormula extends Controller
{
    public function __invoke(
        ChangeIndicatorFormulaRequest $request,
        IndikatorKinerja $indikator,
        ChangeIndicatorFormulaAction $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $hasil = $action->handle($actor, $indikator, $request->validated());
        $indikator = $hasil['indikator'];
        Inertia::flash('indikatorMutation', ['request_id' => $request->input('request_id'), 'status' => $hasil['status'], 'indikator_id' => $indikator->id, 'revision' => $indikator->updated_at?->toISOString()]);

        return redirect()->route(
            $hasil['returnTo'] === 'sasaran-indikator' ? 'perencanaan.sasaran-indikator.index' : 'indikator.komponen.index',
            $hasil['returnTo'] === 'sasaran-indikator' ? ['renstra_id' => $hasil['renstraId']] : ['indikator' => $indikator->id],
        )->with($hasil['status'] === 'unchanged' ? 'message' : 'success', $hasil['status'] === 'unchanged' ? 'Tidak ada perubahan.' : 'Definisi indikator berhasil disimpan.');
    }
}
