<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\UnduhBuktiVersiRencanaAksi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiVersiRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, string $versi, string $bukti, UnduhBuktiVersiRencanaAksi $action): StreamedResponse
    {
        return $action->handle($request->user(), $rencanaAksi, $versi, $bukti);
    }
}
