<?php

namespace App\Http\Controllers\Pengukuran;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditPengukuran extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        $pengukuran = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'periodeJadwal',
            'buktiDukungs',
            'riwayats.user',
        ])->findOrFail($id);

        Gate::authorize('view', $pengukuran);

        return Inertia::render('Pengukuran/Edit', [
            'pengukuran' => $pengukuran,
        ]);
    }
}
