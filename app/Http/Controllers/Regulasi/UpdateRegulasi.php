<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\UpdateRegulasiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\UpdateRegulasiRequest;
use App\Models\Regulasi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateRegulasi extends Controller
{
    public function __invoke(
        UpdateRegulasiRequest $request,
        Regulasi $regulasi,
        UpdateRegulasiAction $action,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $regulasi, $request->validated());

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil diperbarui dan alasan audit telah dicatat.');
    }
}
