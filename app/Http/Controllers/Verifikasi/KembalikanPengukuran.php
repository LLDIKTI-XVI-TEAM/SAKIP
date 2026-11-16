<?php

namespace App\Http\Controllers\Verifikasi;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use App\Models\RiwayatPengukuran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class KembalikanPengukuran extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
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
}
