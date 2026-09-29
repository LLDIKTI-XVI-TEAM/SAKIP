<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\DestroyIndikator as DestroyIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroyIndikator extends Controller
{
    public function __invoke(
        DestroyIndikatorRequest $request,
        IndikatorKinerja $indikator,
        DestroyIndikatorAction $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');
        $result = $action->handle($actor, $indikator, (string) $request->validated('alasan'));

        $message = $result['deactivated']
            ? "Indikator kinerja '{$result['kode']}' dinonaktifkan karena memiliki riwayat data kinerja."
            : "Indikator kinerja '{$result['kode']}' berhasil dihapus.";

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', $message);
    }
}
