<?php

namespace App\Http\Controllers\Verifikasi;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowVerifikasi extends Controller
{
    public function __invoke(Request $request, int $id): Response
    {
        $pengukuran = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'penugasanIndikator.pic',
            'periodeJadwal',
            'buktiDukungs',
            'riwayats.user',
            'snapshot',
        ])->findOrFail($id);

        Gate::authorize('verify', $pengukuran);

        return Inertia::render('Verifikasi/Show', [
            'pengukuran' => $pengukuran,
        ]);
    }
}
