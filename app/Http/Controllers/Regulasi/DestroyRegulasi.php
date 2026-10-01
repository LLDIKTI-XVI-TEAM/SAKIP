<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\DeleteRegulasiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\DeleteRegulasiRequest;
use App\Models\Regulasi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroyRegulasi extends Controller
{
    public function __invoke(
        DeleteRegulasiRequest $request,
        Regulasi $regulasi,
        DeleteRegulasiAction $action,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $regulasi, $request->validated()['alasan']);

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil dihapus.');
    }
}
