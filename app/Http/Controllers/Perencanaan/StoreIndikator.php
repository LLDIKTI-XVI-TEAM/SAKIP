<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\StoreIndikator as StoreIndikatorAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\StoreIndikatorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class StoreIndikator extends Controller
{
    public function __invoke(StoreIndikatorRequest $request, StoreIndikatorAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $hasil = $action->handle($actor, $request->validated());
        $indikator = $hasil['indikator'];
        Inertia::flash('indikatorMutation', ['request_id' => $request->input('request_id'), 'status' => 'saved', 'indikator_id' => $indikator->id, 'revision' => $indikator->updated_at?->toISOString()]);

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $hasil['renstraId']])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil ditambahkan.");
    }
}
