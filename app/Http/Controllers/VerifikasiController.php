<?php

namespace App\Http\Controllers;

use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\RiwayatPengukuran;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VerifikasiController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if (!$user->hasRole('perencanaan') && !$user->hasRole('superadmin')) {
            abort(403, 'Akses terbatas untuk Tim Perencanaan.');
        }

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

    public function show(Request $request, int $id): Response
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

    public function kembalikan(Request $request, int $id): RedirectResponse
    {
        $pengukuran = PengukuranKinerja::findOrFail($id);
        Gate::authorize('verify', $pengukuran);

        $validated = $request->validate([
            'catatan' => ['required', 'string', 'min:10'],
        ]);

        DB::transaction(function () use ($request, $pengukuran, $validated) {
            $statusSebelum = $pengukuran->status;

            $pengukuran->update([
                'status' => 'dikembalikan',
            ]);

            RiwayatPengukuran::create([
                'pengukuran_kinerja_id' => $pengukuran->id,
                'user_id' => $request->user()->id,
                'status_dari' => $statusSebelum,
                'status_ke' => 'dikembalikan',
                'catatan' => $validated['catatan'],
            ]);
        });

        return redirect()->route('verifikasi.index')->with('success', 'Kinerja berhasil dikembalikan ke PIC untuk perbaikan.');
    }

    public function sahkan(Request $request, int $id): RedirectResponse
    {
        $pengukuran = PengukuranKinerja::with([
            'penugasanIndikator.indikatorKinerja.sasaranStrategis',
            'penugasanIndikator.unitKerja',
            'penugasanIndikator.pic',
            'periodeJadwal',
            'buktiDukungs',
        ])->findOrFail($id);

        Gate::authorize('ratify', $pengukuran);

        $user = $request->user();
        $now = Carbon::now();

        DB::transaction(function () use ($pengukuran, $user, $now) {
            $statusSebelum = $pengukuran->status;

            $pengukuran->update([
                'status' => 'disahkan',
                'disahkan_oleh' => $user->id,
                'disahkan_pada' => $now,
            ]);

            // Catat Riwayat Pengesahan
            RiwayatPengukuran::create([
                'pengukuran_kinerja_id' => $pengukuran->id,
                'user_id' => $user->id,
                'status_dari' => $statusSebelum,
                'status_ke' => 'disahkan',
                'catatan' => 'Kinerja disahkan secara resmi oleh Tim Perencanaan.',
            ]);

            // Bekukan data ke JSONB Snapshot Immutability
            $snapshotPayload = [
                'indikator' => [
                    'id' => $pengukuran->penugasanIndikator->indikatorKinerja->id,
                    'kode' => $pengukuran->penugasanIndikator->indikatorKinerja->kode,
                    'nama' => $pengukuran->penugasanIndikator->indikatorKinerja->nama,
                    'satuan' => $pengukuran->penugasanIndikator->indikatorKinerja->satuan,
                    'tipe_perhitungan' => $pengukuran->penugasanIndikator->indikatorKinerja->tipe_perhitungan,
                ],
                'unit_kerja' => [
                    'id' => $pengukuran->penugasanIndikator->unitKerja->id,
                    'nama' => $pengukuran->penugasanIndikator->unitKerja->nama,
                ],
                'pic' => [
                    'id' => $pengukuran->penugasanIndikator->pic?->id,
                    'name' => $pengukuran->penugasanIndikator->pic?->name,
                ],
                'periode' => [
                    'tahun' => $pengukuran->periodeJadwal->tahun,
                    'triwulan' => $pengukuran->periodeJadwal->triwulan,
                    'nama_periode' => $pengukuran->periodeJadwal->nama_periode,
                ],
                'target' => $pengukuran->target,
                'realisasi' => $pengukuran->realisasi,
                'capaian_persen' => $pengukuran->capaian_persen,
                'kendala' => $pengukuran->kendala,
                'tindak_lanjut' => $pengukuran->tindak_lanjut,
                'strategi' => $pengukuran->strategi,
                'bukti_dukungs' => $pengukuran->buktiDukungs->map(fn ($b) => [
                    'nama_file' => $b->nama_file,
                    'url_tautan' => $b->url_tautan,
                    'file_path' => $b->file_path,
                ])->toArray(),
                'disahkan_oleh' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'jabatan' => $user->jabatan,
                ],
                'disahkan_pada' => $now->toIso8601String(),
            ];

            $hash = hash('sha256', json_encode($snapshotPayload));

            KinerjaSnapshot::updateOrCreate(
                ['pengukuran_kinerja_id' => $pengukuran->id],
                [
                    'snapshot_hash' => $hash,
                    'snapshot_data' => $snapshotPayload,
                    'disahkan_oleh' => $user->id,
                    'disahkan_pada' => $now,
                ]
            );
        });

        return redirect()->route('verifikasi.index')->with('success', 'Capaian kinerja telah resmi disahkan dan snapshot imutabel tersimpan.');
    }
}
