<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\ChangeIndicatorFormula as ChangeIndicatorFormulaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\UpdateIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UpdateIndikator extends Controller
{
    public function __invoke(
        UpdateIndikatorRequest $request,
        IndikatorKinerja $indikator,
        ChangeIndicatorFormulaAction $action
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validated();
        $kembaliKeDetail = ($data['kembali'] ?? null) === 'detail';
        unset($data['kembali']);

        $hasil = $action->handle(
            $actor,
            $indikator,
            [...$data, 'return_to' => 'sasaran-indikator'],
        );
        $indikator = $hasil['indikator'];
        Inertia::flash('indikatorMutation', ['request_id' => $request->input('request_id'), 'status' => $hasil['status'], 'indikator_id' => $indikator->id, 'revision' => $indikator->updated_at?->toISOString()]);

        return ($kembaliKeDetail
            ? redirect()->route('perencanaan.indikator.show', $indikator)
            : redirect()->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $hasil['renstraId']]))
            ->with($hasil['status'] === 'unchanged' ? 'message' : 'success', $hasil['status'] === 'unchanged' ? 'Tidak ada perubahan.' : "Indikator kinerja '{$indikator->kode}' berhasil diperbarui.");
    }
}
