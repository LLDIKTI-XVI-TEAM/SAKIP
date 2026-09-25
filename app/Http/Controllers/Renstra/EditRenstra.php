<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra): Response
    {
        Gate::authorize('update', $renstra);

        $renstra->load([
            'regulasi',
            'berkas' => fn ($query) => $query->with('pengunggah')->orderByDesc('created_at'),
        ]);

        $regulasiPilihan = Regulasi::query()
            ->where('aktif', true)
            ->orWhere('id', $renstra->regulasi_id)
            ->orderBy('tahun', 'desc')
            ->orderBy('nomor')
            ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);

        return Inertia::render('Renstra/Edit', [
            'renstra' => $renstra,
            'regulasiPilihan' => $regulasiPilihan,
        ]);
    }
}
