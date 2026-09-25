<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Renstra;
use App\Models\User;
use App\Services\RenstraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DestroyBerkasRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra, Berkas $berkas, RenstraService $service): RedirectResponse
    {
        Gate::authorize('deleteAttachment', [$renstra, $berkas]);

        if ($berkas->berkasable_type !== $renstra->getMorphClass() || $berkas->berkasable_id !== $renstra->id) {
            abort(404, 'Lampiran tidak terkait dengan Renstra ini.');
        }

        $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan penghapusan lampiran wajib diisi.',
            'alasan.min' => 'Alasan penghapusan lampiran minimal 5 karakter.',
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $service->deleteAttachment($renstra, $berkas, (string) $request->input('alasan'), $actor);

        return back()->with('success', 'Lampiran dokumen Renstra berhasil dihapus.');
    }
}
