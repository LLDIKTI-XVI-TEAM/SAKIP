<?php

namespace App\Http\Controllers\Pengukuran;

use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiKlaimPengukuran extends Controller
{
    public function __invoke(string $id, string $buktiId): StreamedResponse
    {
        $p = PengukuranKinerja::findOrFail($id);
        Gate::authorize('viewClaimEvidence', $p);
        // Keanggotaan dan metadata berasal dari versi milik pengukuran, bukan berkas kegiatan live.
        $version = $p->versions()->whereJsonContains('snapshot->klaim', [['kegiatan' => ['bukti_dukungs' => [['id' => $buktiId]]]]])
            ->orderByDesc('nomor')->first(['snapshot']);
        $bukti = collect($version?->snapshot['klaim'] ?? [])->flatMap(fn ($claim) => $claim['kegiatan']['bukti_dukungs'])->firstWhere('id', $buktiId);
        abort_unless($bukti && $bukti['mode'] === 'file' && $bukti['path'] && Storage::disk('local')->exists($bukti['path']), 404);

        return Storage::disk('local')->download($bukti['path'], $bukti['nama_asli'], ['Cache-Control' => 'private, no-store']);
    }
}
