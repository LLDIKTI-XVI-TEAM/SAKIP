<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\StoreRenstraRequest;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;

class StoreRenstra extends Controller
{
    public function __invoke(StoreRenstraRequest $request, RenstraService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $renstra = $service->create($request->validated(), $actor);

        return redirect()
            ->route('renstra.index')
            ->with('success', 'Rencana Strategis (Renstra) berhasil dibuat dengan status Draft.');
    }
}
