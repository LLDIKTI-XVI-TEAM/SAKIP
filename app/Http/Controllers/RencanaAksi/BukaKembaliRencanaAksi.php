<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\BukaKembaliRencanaAksi as BukaKembali;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\ReviewRencanaAksiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class BukaKembaliRencanaAksi extends Controller
{
    public function __invoke(ReviewRencanaAksiRequest $request, string $rencanaAksi, BukaKembali $action): RedirectResponse|JsonResponse
    {
        $header = $action->handle($request->user(), $rencanaAksi, $request->validated());
        if ($request->expectsJson()) {
            return response()->json(['status_alur' => $header->status_alur, 'versi' => $header->versi]);
        }

        return redirect()->route('rencana-aksi.review', $rencanaAksi)->with('success', 'Rencana aksi dibuka kembali untuk diperbaiki.');
    }
}
