<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\UpdateRenstraAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Renstra\UpdateRenstraRequest;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UpdateRenstra extends Controller
{
    public function __invoke(UpdateRenstraRequest $request, Renstra $renstra, UpdateRenstraAction $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $updated = $action->handle($actor, $renstra, $data);
        $changed = $updated->wasChanged() || ! empty($data['lampiran']);

        Inertia::flash('success', $changed ? 'Rencana Strategis (Renstra) berhasil diperbarui.' : 'Tidak ada perubahan master yang disimpan.');

        return redirect()->route('renstra.show', $renstra->id);
    }
}
