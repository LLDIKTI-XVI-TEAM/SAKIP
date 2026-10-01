<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\CreateRegulasiAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\StoreRegulasiRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreRegulasi extends Controller
{
    public function __invoke(StoreRegulasiRequest $request, CreateRegulasiAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $request->validated());

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil ditambahkan.');
    }
}
