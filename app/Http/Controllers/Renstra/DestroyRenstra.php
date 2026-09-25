<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DestroyRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra, RenstraService $service): RedirectResponse
    {
        Gate::authorize('delete', $renstra);

        $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan penghapusan Renstra wajib diisi.',
            'alasan.min' => 'Alasan penghapusan minimal 5 karakter.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $service->delete($renstra, (string) $request->input('alasan'), $actor);

        return redirect()
            ->route('renstra.index')
            ->with('success', 'Rencana Strategis (Renstra) berhasil dihapus.');
    }
}
