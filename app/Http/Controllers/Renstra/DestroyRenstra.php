<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\DeleteRenstraAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\DestroyRenstraRequest;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class DestroyRenstra extends Controller
{
    public function __invoke(DestroyRenstraRequest $request, Renstra $renstra, DeleteRenstraAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $renstra, (string) $request->validated('alasan'));

        return redirect()
            ->route('renstra.index')
            ->with('success', 'Rencana Strategis (Renstra) berhasil dihapus.');
    }
}
