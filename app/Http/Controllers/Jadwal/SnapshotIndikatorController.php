<?php

namespace App\Http\Controllers\Jadwal;

use App\Actions\Jadwal\KoreksiSnapshot;
use App\Actions\Jadwal\ShowSnapshotIndikator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jadwal\KoreksiSnapshotRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SnapshotIndikatorController extends Controller
{
    public function index(Request $request, string $jadwal, ShowSnapshotIndikator $action): Response
    {
        return Inertia::render('Jadwal/SnapshotIndikator', $action->handle($request->user(), $jadwal));
    }

    public function koreksi(KoreksiSnapshotRequest $request, string $jadwal, string $snapshot, KoreksiSnapshot $action): RedirectResponse
    {
        $action->handle($request->user(), $jadwal, $snapshot, $request->validated());
        Inertia::flash('success', 'Versi koreksi snapshot berhasil diterbitkan.');

        return redirect()->route('jadwal.snapshot.index', $jadwal);
    }
}
