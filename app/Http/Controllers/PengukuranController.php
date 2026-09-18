<?php

namespace App\Http\Controllers;

use App\Models\BuktiDukung;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RiwayatPengukuran;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PengukuranController extends Controller
{
    public function index(Request $request): Response
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

        if ($user && $user->hasRole('pegawai') && !$user->hasRole('superadmin')) {
            $query->whereHas('penugasanIndikator', function ($q) use ($user) {
                $q->where('unit_kerja_id', $user->unit_kerja_id);
            });
        }

        return Inertia::render('Pengukuran/Index', [
            'periode' => $periode,
            'pengukurans' => $query->get(),
        ]);
    }

    public function edit(Request $request, int $id): Response
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

    public function update(Request $request, int $id): RedirectResponse
    {
        $pengukuran = PengukuranKinerja::with('penugasanIndikator.indikatorKinerja', 'periodeJadwal')->findOrFail($id);
        Gate::authorize('update', $pengukuran);

        $action = $request->input('action', 'draft'); // 'draft' atau 'ajukan'

        $rules = [
            'realisasi' => ['required', 'numeric', 'min:0'],
            'file_bukti' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,docx'],
            'url_bukti' => ['nullable', 'url', 'max:500'],
            'keterangan_bukti' => ['nullable', 'string', 'max:255'],
        ];

        // Jika diajukan, validasi catatan jika capaian < 100%
        $tipe = $pengukuran->penugasanIndikator->indikatorKinerja->tipe_perhitungan;
        $realisasi = (float) $request->input('realisasi');
        $capaian = PengukuranKinerja::hitungCapaian($pengukuran->target, $realisasi, $tipe);

        if ($action === 'ajukan' && $capaian !== null && $capaian < 100.0) {
            $rules['kendala'] = ['required', 'string', 'min:10'];
            $rules['tindak_lanjut'] = ['required', 'string', 'min:10'];
            $rules['strategi'] = ['required', 'string', 'min:10'];
        } else {
            $rules['kendala'] = ['nullable', 'string'];
            $rules['tindak_lanjut'] = ['nullable', 'string'];
            $rules['strategi'] = ['nullable', 'string'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($request, $pengukuran, $validated, $capaian, $action) {
            $statusSebelum = $pengukuran->status;
            $statusBaru = $action === 'ajukan' ? 'diajukan' : $pengukuran->status;

            $pengukuran->update([
                'realisasi' => $validated['realisasi'],
                'capaian_persen' => $capaian,
                'status' => $statusBaru,
                'kendala' => $validated['kendala'] ?? null,
                'tindak_lanjut' => $validated['tindak_lanjut'] ?? null,
                'strategi' => $validated['strategi'] ?? null,
                'diajukan_pada' => $action === 'ajukan' ? Carbon::now() : $pengukuran->diajukan_pada,
            ]);

            // Handle Upload File Bukti Fisik
            if ($request->hasFile('file_bukti')) {
                $file = $request->file('file_bukti');
                $path = $file->store('bukti-dukung', 'public');

                BuktiDukung::create([
                    'pengukuran_kinerja_id' => $pengukuran->id,
                    'nama_file' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'tipe_file' => $file->getClientOriginalExtension(),
                    'file_size' => $file->getSize(),
                    'keterangan' => $validated['keterangan_bukti'] ?? 'Dokumen Bukti Fisik',
                ]);
            }

            // Handle URL Link Bukti Eksternal
            if (!empty($validated['url_bukti'])) {
                BuktiDukung::create([
                    'pengukuran_kinerja_id' => $pengukuran->id,
                    'nama_file' => $validated['keterangan_bukti'] ?: 'Tautan Google Drive / Cloud Eksternal',
                    'file_path' => null,
                    'tipe_file' => 'link',
                    'file_size' => 0,
                    'url_tautan' => $validated['url_bukti'],
                    'keterangan' => $validated['keterangan_bukti'] ?? 'Tautan Cloud',
                ]);
            }

            // Catat Riwayat Transisi jika diajukan
            if ($action === 'ajukan' && $statusSebelum !== 'diajukan') {
                RiwayatPengukuran::create([
                    'pengukuran_kinerja_id' => $pengukuran->id,
                    'user_id' => $request->user()->id,
                    'status_dari' => $statusSebelum,
                    'status_ke' => 'diajukan',
                    'catatan' => 'Kinerja diajukan ke Tim Perencanaan untuk diverifikasi.',
                ]);
            }
        });

        $message = $action === 'ajukan'
            ? 'Capaian kinerja berhasil diajukan untuk verifikasi.'
            : 'Draft pengukuran kinerja berhasil disimpan.';

        return redirect()->route('pengukuran.index')->with('success', $message);
    }
}
