<?php

namespace App\Http\Controllers\Verifikasi;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexVerifikasi extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('view-verifikasi');

        $pengukurans = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'penugasanIndikator.pic',
            'periodeJadwal',
            'buktiDukungs',
        ])
            ->whereIn('status', ['diajukan', 'diverifikasi'])
            ->orderByDesc('diajukan_pada')
            ->get();

        return Inertia::render('Verifikasi/Index', [
            'pengukurans' => $pengukurans,
        ]);
    }
}
