<?php

namespace App\Http\Controllers\Rekomendasi;

use App\Actions\Rekomendasi\IndexRekomendasi;
use App\Actions\Rekomendasi\ShowRekomendasi;
use App\Actions\Rekomendasi\TetapkanRekomendasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rekomendasi\TetapkanRekomendasiRequest;
use App\Models\RekomendasiPimpinan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RekomendasiController extends Controller
{
    public function index(Request $request, IndexRekomendasi $action): Response
    {
        return Inertia::render('Rekomendasi/Index', $action->handle($request->user(), $request->query()));
    }

    public function store(TetapkanRekomendasiRequest $request, TetapkanRekomendasi $action): RedirectResponse
    {
        $row = $action->handle($request->user(), $request->validated());
        Inertia::flash('success', $row->status === 'disetujui' ? 'Rekomendasi berhasil disetujui.' : 'Draf rekomendasi berhasil disimpan.');

        return back();
    }

    public function show(Request $request, RekomendasiPimpinan $rekomendasi, ShowRekomendasi $action): Response
    {
        return Inertia::render('Rekomendasi/Show', $action->handle($request->user(), $rekomendasi, $request->query()));
    }
}
