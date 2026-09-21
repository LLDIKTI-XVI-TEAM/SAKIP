<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\StoreRegulasiRequest;
use App\Models\User;
use App\Services\RegulasiService;
use Illuminate\Http\RedirectResponse;

class StoreRegulasi extends Controller
{
    public function __invoke(StoreRegulasiRequest $request, RegulasiService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $service->create($request->validated(), $actor);

        return redirect()
            ->route('regulasi.index')
            ->with('success', 'Dasar aturan berhasil ditambahkan.');
    }
}
