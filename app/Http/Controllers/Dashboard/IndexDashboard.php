<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexDashboard extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $activeRenstra = Renstra::where('is_aktif', true)->first();
        $activePeriode = PeriodeJadwal::where('status', 'buka')->where('is_tahun_ditutup', false)->first()
            ?? PeriodeJadwal::latest()->first();

        $query = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'penugasanIndikator.pic',
            'periodeJadwal',
            'buktiDukungs',
        ]);

        if ($activePeriode) {
            $query->where('periode_jadwal_id', $activePeriode->id);
        }

        // Jika user adalah PIC (pegawai biasa), fokuskan data unit kerjanya
        if ($user && $user->hasRole('pegawai') && ! $user->hasRole('superadmin')) {
            $query->whereHas('penugasanIndikator', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        $pengukurans = $query->get();

        // Rekapitulasi Statistik
        $totalIndikator = $pengukurans->count();
        $draftCount = $pengukurans->where('status', 'draft')->count();
        $diajukanCount = $pengukurans->where('status', 'diajukan')->count();
        $dikembalikanCount = $pengukurans->where('status', 'dikembalikan')->count();
        $disahkanCount = $pengukurans->where('status', 'disahkan')->count();

        $validCapaian = $pengukurans->whereNotNull('capaian_persen');
        $rataRataCapaian = $validCapaian->count() > 0 ? round($validCapaian->avg('capaian_persen'), 2) : 0.0;

        return Inertia::render('Dashboard/Index', [
            'activeRenstra' => $activeRenstra,
            'activePeriode' => $activePeriode,
            'stats' => [
                'total' => $totalIndikator,
                'draft' => $draftCount,
                'diajukan' => $diajukanCount,
                'dikembalikan' => $dikembalikanCount,
                'disahkan' => $disahkanCount,
                'rata_rata_capaian' => $rataRataCapaian,
            ],
            'pengukurans' => $pengukurans,
        ]);
    }
}
