<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\UpdateIndikator as UpdateIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\UpdateIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateIndikator extends Controller
{
    public function __invoke(
        UpdateIndikatorRequest $request,
        IndikatorKinerja $indikator,
        UpdateIndikatorAction $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $indikator = $action->handle(
            $actor,
            $indikator,
            $request->validated(),
            $request->only(['alasan_pindah_unit', 'alasan']),
        );
        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil diperbarui.");
    }
}
