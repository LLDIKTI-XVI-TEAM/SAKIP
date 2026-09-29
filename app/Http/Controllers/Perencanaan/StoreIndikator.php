<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\StoreIndikator as StoreIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\StoreIndikatorRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreIndikator extends Controller
{
    public function __invoke(StoreIndikatorRequest $request, StoreIndikatorAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $indikator = $action->handle($actor, $request->validated());
        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil ditambahkan.");
    }
}
