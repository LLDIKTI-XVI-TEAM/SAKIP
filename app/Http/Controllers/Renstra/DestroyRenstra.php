<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\DestroyRenstraRequest;
use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;

class DestroyRenstra extends Controller
{
    public function __invoke(DestroyRenstraRequest $request, Renstra $renstra, RenstraService $service): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $service->delete($renstra, (string) $request->input('alasan'), $actor);

        return redirect()
            ->route('renstra.index')
            ->with('success', 'Rencana Strategis (Renstra) berhasil dihapus.');
    }
}
