<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\UpdateRegulasiRequest;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\RegulasiService;
use Illuminate\Http\RedirectResponse;

class UpdateRegulasi extends Controller
{
    public function __invoke(
        UpdateRegulasiRequest $request,
        Regulasi $regulasi,
        RegulasiService $service,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $service->update($regulasi, $request->validated(), $actor);

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil diperbarui dan alasan audit telah dicatat.');
    }
}
