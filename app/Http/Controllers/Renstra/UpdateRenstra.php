<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\UpdateRenstraAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\UpdateRenstraRequest;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class UpdateRenstra extends Controller
{
    public function __invoke(UpdateRenstraRequest $request, Renstra $renstra, UpdateRenstraAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $renstra, $request->validated());

        return redirect()
            ->route('renstra.show', $renstra->id)
            ->with('success', 'Rencana Strategis (Renstra) berhasil diperbarui.');
    }
}
