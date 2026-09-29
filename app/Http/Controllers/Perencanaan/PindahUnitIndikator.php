<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\PindahUnitIndikator as PindahUnitIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\PindahUnitIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
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

        $indikator = $action->handle($actor, $indikator, $request->validated());
        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Unit penanggung jawab indikator '{$indikator->kode}' berhasil dipindahkan.");
    }
}
