<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\DestroyIndikator as DestroyIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorRequest;
use App\Models\IndikatorKinerja;
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

        $hasil = $action->handle($actor, $indikator, (string) $request->validated('alasan'));

        $message = "Indikator kinerja '{$hasil['kode']}' telah diarsipkan dan tidak lagi menerima target pengisian baru.";

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $hasil['renstraId']])
            ->with('success', $message);
    }
}
