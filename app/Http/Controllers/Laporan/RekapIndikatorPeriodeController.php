<?php

namespace App\Http\Controllers\Laporan;

use App\Actions\Laporan\TampilkanRekapIndikatorPeriode;
use App\Http\Requests\Laporan\RekapIndikatorPeriodeRequest;
use Inertia\Inertia;
use Inertia\Response;

class RekapIndikatorPeriodeController
{
    public function __invoke(RekapIndikatorPeriodeRequest $request, TampilkanRekapIndikatorPeriode $rekap): Response
    {
        return Inertia::render('Laporan/Rekap', $rekap->handle($request->user(), $request->validated()));
    }
}
