<?php

namespace App\Http\Controllers\Pengukuran;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiPengukuran extends Controller
{
    public function __invoke(string $id, string $buktiId): StreamedResponse
    {
        $p = PengukuranKinerja::findOrFail($id);
        Gate::authorize('viewEvidence', $p);
        if (in_array($p->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true)) {
            $bukti = collect($p->latestVersion?->snapshot['bukti_dukungs'] ?? [])->firstWhere('id', $buktiId);
        } else {
            $bukti = $p->buktiDukungs()->findOrFail($buktiId)->only(['mode', 'path', 'nama_asli']);
        }
        abort_unless($bukti && $bukti['mode'] === 'file' && $bukti['path'] && Storage::disk('local')->exists($bukti['path']), 404);

        return Storage::disk('local')->download($bukti['path'], $bukti['nama_asli'], ['Cache-Control' => 'private, no-store']);
    }
}
