<?php

namespace App\Http\Controllers\Pengukuran;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengukuran extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $periode = PeriodeJadwal::where('status', 'buka')->where('is_tahun_ditutup', false)->first()
            ?? PeriodeJadwal::latest()->first();

        $query = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'periodeJadwal',
            'buktiDukungs',
        ]);

        if ($periode) {
            $query->where('periode_jadwal_id', $periode->id);
        }

        if ($user && $user->hasRole('pegawai') && ! $user->hasRole('superadmin')) {
            $query->whereHas('penugasanIndikator', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        return Inertia::render('Pengukuran/Index', [
            'periode' => $periode,
            'pengukurans' => $query->get(),
        ]);
    }
}
