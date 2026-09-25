<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CreateRenstra extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('create', Renstra::class);

        $regulasiPilihan = Regulasi::query()
            ->where('aktif', true)
            ->orderBy('tahun', 'desc')
            ->orderBy('nomor')
            ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);

        return Inertia::render('Renstra/Create', [
            'regulasiPilihan' => $regulasiPilihan,
        ]);
    }
}
