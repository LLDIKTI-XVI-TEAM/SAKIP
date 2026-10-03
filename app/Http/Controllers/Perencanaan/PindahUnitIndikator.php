<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\PindahUnitIndikator as PindahUnitIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\PindahUnitIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PindahUnitIndikator extends Controller
{
    public function __invoke(
        PindahUnitIndikatorRequest $request,
        IndikatorKinerja $indikator,
        PindahUnitIndikatorAction $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $hasil = $action->handle($actor, $indikator, $request->validated());
        $indikator = $hasil['indikator'];

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $hasil['renstraId']])
            ->with('success', "Unit penanggung jawab indikator '{$indikator->kode}' berhasil dipindahkan.");
    }
}
