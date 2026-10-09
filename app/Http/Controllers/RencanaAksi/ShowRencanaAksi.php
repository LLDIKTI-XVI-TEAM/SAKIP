<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\IndexBuktiRencanaAksi;
use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    /**
     * T7: header pra-transaksi hanya untuk 404 + otorisasi view (`unit_id`
     * imutabel pasca-create sehingga aman). Konsistensi payload (versi vs
     * target) ditegakkan di dalam `IndexRencanaAksi::handle` via transaksi
     * baca + `sharedLock` + verifikasi versi — model ini tidak diteruskan
     * apa adanya ke payload.
     */
    public function __invoke(Request $request, string $rencanaAksi, IndexRencanaAksi $index, IndexBuktiRencanaAksi $bukti): Response
    {
        $header = RencanaAksi::findOrFail($rencanaAksi);
        Gate::authorize('view', $header);

        return Inertia::render('RencanaAksi/Show', [
            'rencanaAksi' => $index->handle($request->user(), $header),
            'bukti' => $bukti->handle($request->user(), $header),
        ]);
    }
}
