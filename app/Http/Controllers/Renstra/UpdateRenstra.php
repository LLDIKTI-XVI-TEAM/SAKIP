<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\UpdateRenstraRequest;
use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;

class UpdateRenstra extends Controller
{
    public function __invoke(UpdateRenstraRequest $request, Renstra $renstra, RenstraService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $service->update($renstra, $request->validated(), $actor);

        return redirect()
            ->route('renstra.show', $renstra->id)
            ->with('success', 'Rencana Strategis (Renstra) berhasil diperbarui.');
    }
}
