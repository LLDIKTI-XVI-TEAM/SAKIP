<?php

namespace App\Http\Controllers\Jadwal;

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\ListJadwal;
use App\Actions\Jadwal\ReadJadwalActivation;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\SearchJadwalOptions;
use App\Actions\Jadwal\ShowJadwalEditor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Jadwal\ActivateJadwalRequest;
use App\Http\Requests\Jadwal\StoreJadwalRequest;
use App\Http\Requests\Jadwal\UpdateJadwalRequest;
use App\Models\JadwalTahunan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JadwalController extends Controller
{
    public function index(Request $request, ListJadwal $action): Response
    {
        return Inertia::render('Jadwal/Index', $action->handle($request->user(), $request->query()));
    }

    public function create(Request $request, ShowJadwalEditor $action): Response
    {
        return Inertia::render('Jadwal/Editor', $action->handle($request->user()));
    }

    public function show(Request $request, string $jadwal, ShowJadwalEditor $action): Response
    {
        return Inertia::render('Jadwal/Editor', $action->handle($request->user(), (new JadwalTahunan)->forceFill(['id' => $jadwal])));
    }

    public function options(Request $request, string $jenis, SearchJadwalOptions $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $jenis, $request->query()));
    }

    public function readiness(Request $request, string $jadwal, ReadJadwalActivation $action): JsonResponse
    {
        return response()->json($action->handle($request->user(), $jadwal));
    }

    /** Hasil diikat operation_id + jadwal_id; klien hanya menerima sukses dari flash yang cocok dengan request-nya. */
    public function activate(ActivateJadwalRequest $request, string $jadwal, ActivateJadwal $action): RedirectResponse
    {
        Inertia::flash('jadwal_aktivasi', $action->handle($request->user(), $jadwal, $request->validated()));

        return redirect()->route('jadwal.show', $jadwal);
    }

    public function store(StoreJadwalRequest $request, SaveJadwalDraft $action): RedirectResponse
    {
        $jadwal = $action->handle($request->user(), $request->validated());
        Inertia::flash('success', 'Draft jadwal berhasil disimpan.');

        return redirect()->route('jadwal.show', $jadwal);
    }

    public function update(UpdateJadwalRequest $request, string $jadwal, SaveJadwalDraft $action): RedirectResponse
    {
        $saved = $action->handle($request->user(), $request->validated(), (new JadwalTahunan)->forceFill(['id' => $jadwal]));
        Inertia::flash('success', 'Draft jadwal berhasil disimpan.');

        return redirect()->route('jadwal.show', $saved);
    }
}
