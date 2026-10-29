<?php

namespace App\Http\Controllers\Laporan;

use App\Actions\Laporan\EksporRekapIndikatorPeriode;
use App\Http\Requests\Laporan\EksporRekapIndikatorPeriodeRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EksporRekapIndikatorPeriodeController
{
    public function __invoke(EksporRekapIndikatorPeriodeRequest $request, EksporRekapIndikatorPeriode $ekspor): StreamedResponse
    {
        $berkas = $ekspor->handle($request->user(), $request->validated());

        return response()->streamDownload(function () use ($berkas): void {
            echo $berkas['isi'];
        }, $berkas['nama'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
