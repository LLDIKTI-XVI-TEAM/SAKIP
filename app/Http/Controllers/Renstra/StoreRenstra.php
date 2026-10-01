<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\CreateRenstraAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\StoreRenstraRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class StoreRenstra extends Controller
{
    public function __invoke(StoreRenstraRequest $request, CreateRenstraAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $request->validated());

        return redirect()
            ->route('renstra.index')
            ->with('success', 'Rencana Strategis (Renstra) berhasil dibuat dengan status Draft.');
    }
}
