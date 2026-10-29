<?php

namespace App\Http\Controllers\TargetRenstra;

use App\Actions\TargetRenstra\BacaHistoriRevisiTargetRenstra;
use App\Actions\TargetRenstra\BatalRevisiTargetRenstra;
use App\Actions\TargetRenstra\MulaiRevisiTargetRenstra;
use App\Actions\TargetRenstra\SimpanDrafRevisiTargetRenstra;
use App\Actions\TargetRenstra\SimpanRevisiTargetRenstra;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Izin, state, dan validasi dinilai Action di dalam transaksi agar setiap penolakan teraudit. */
class RevisiTargetRenstraController extends Controller
{
    public function histori(Request $request, string $renstra, BacaHistoriRevisiTargetRenstra $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $renstra));
    }

    public function mulai(Request $request, string $renstra, MulaiRevisiTargetRenstra $action): RedirectResponse
    {
        $action->handle($request->user(), $renstra);

        return $this->kembali($renstra);
    }

    public function draf(Request $request, string $renstra, SimpanDrafRevisiTargetRenstra $action): RedirectResponse
    {
        $action->handle($request->user(), $renstra, $request->except(['_token', '_method']));

        return $this->kembali($renstra);
    }

    public function simpan(Request $request, string $renstra, SimpanRevisiTargetRenstra $action): RedirectResponse
    {
        $revisi = $action->handle($request->user(), $renstra, $request->except(['_token', '_method']));
        Inertia::flash('success', "Revisi ke-{$revisi->nomor} target Renstra tersimpan.");

        return $this->kembali($renstra);
    }

    public function batal(Request $request, string $renstra, BatalRevisiTargetRenstra $action): RedirectResponse
    {
        $action->handle($request->user(), $renstra, $request->except(['_token', '_method']));
        Inertia::flash('success', 'Draf revisi target Renstra dibatalkan.');

        return $this->kembali($renstra);
    }

    private function kembali(string $renstra): RedirectResponse
    {
        return redirect()->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstra]);
    }
}
