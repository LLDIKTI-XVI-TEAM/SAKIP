<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\DeleteRegulasiRequest;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\RegulasiService;
use Illuminate\Http\RedirectResponse;

class DestroyRegulasi extends Controller
{
    public function __invoke(
        DeleteRegulasiRequest $request,
        Regulasi $regulasi,
        RegulasiService $service,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $service->delete($regulasi, $request->string('alasan')->toString(), $actor);

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil dihapus.');
    }
}
