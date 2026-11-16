<?php

namespace App\Http\Controllers\Periode;

use App\Actions\Periode\ListPeriode;
use App\Actions\Periode\ReplaceFinalPeriode;
use App\Actions\Periode\SavePeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Periode\ReplaceFinalPeriodeRequest;
use App\Http\Requests\Periode\StorePeriodeRequest;
use App\Http\Requests\Periode\UpdatePeriodeRequest;
use App\Models\Periode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodeController extends Controller
{
    public function index(Request $request, ListPeriode $action): Response
    {
        return Inertia::render('Periode/Index', $action->handle($request->user(), $request->query()));
    }

    public function store(StorePeriodeRequest $request, SavePeriode $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Inertia::flash('success', 'Periode berhasil disimpan.');

        return redirect()->route('periode.index');
    }

    public function update(UpdatePeriodeRequest $request, string $periode, SavePeriode $action): RedirectResponse
    {
        // Target belum di-query: Action memeriksa izin hidup sebelum mencari row.
        $action->handle($request->user(), $request->validated(), (new Periode)->forceFill(['id' => $periode]));
        Inertia::flash('success', 'Periode berhasil disimpan.');

        return redirect()->route('periode.index');
    }

    public function replaceFinal(ReplaceFinalPeriodeRequest $request, ReplaceFinalPeriode $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        Inertia::flash('success', 'Periode nilai akhir berhasil diganti.');

        return redirect()->route('periode.index');
    }
}
