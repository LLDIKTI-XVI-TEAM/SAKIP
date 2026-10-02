<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\ChangeIndicatorFormula as ChangeIndicatorFormulaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\ChangeIndicatorFormulaRequest;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

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

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $hasil['renstraId']])
            ->with('success', "Formula perhitungan indikator '{$indikator->kode}' berhasil diubah.");
    }
}
