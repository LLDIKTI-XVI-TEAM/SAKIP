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

        if ($renstra->status === Renstra::STATUS_DIARSIPKAN) {
            abort(403, 'Renstra yang telah diarsipkan bersifat permanen dan tidak dapat diubah.');
        }

        $dapatBacaRegulasi = $request->user()?->can('viewAny', Regulasi::class) ?? false;

        if ($dapatBacaRegulasi) {
            $renstra->load('regulasi');
            $regulasiPilihan = Regulasi::query()
                ->where('aktif', true)
                ->orWhere('id', $renstra->regulasi_id)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);
        } else {
            $renstra->setRelation('regulasi', null);
            $regulasiPilihan = [];
        }

        return Inertia::render('Renstra/Edit', [
            'renstra' => $renstra,
            'regulasiPilihan' => $regulasiPilihan,
        ]);
    }
}
