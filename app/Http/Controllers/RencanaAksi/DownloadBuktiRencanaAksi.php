<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiRencanaAksi extends Controller
{
    public function __invoke(string $id, string $buktiId): StreamedResponse
    {
        $ra = RencanaAksi::findOrFail($id);
        Gate::authorize('viewEvidence', $ra);
        // Keanggotaan diresolusi dari snapshot versi (metadata beku) agar bukti historis
        // tetap memakai path saat diajukan; fallback relasi live hanya untuk status non-beku.
        // Unduhan dibatasi ke versi yang sedang direviu (nomor terbesar) atau versi resmi
        // tersahkan; versi superseded non-resmi tidak dapat dijangkau lewat URL lama
        // (temuan review 2026-10-09).
        $version = RencanaAksiVersi::query()
            ->where('rencana_aksi_id', $ra->id)
            ->whereJsonContains('snapshot->bukti_dukungs', [['id' => $buktiId]])
            ->where(fn (EloquentBuilder $query) => $query
                ->where('nomor', RencanaAksiVersi::query()->select('nomor')->where('rencana_aksi_id', $ra->id)->orderByDesc('nomor')->limit(1))
                ->orWhereNotNull('disahkan_at'))
            ->orderByDesc('nomor')->first(['snapshot']);
        $bukti = collect($version?->snapshot['bukti_dukungs'] ?? [])->firstWhere('id', $buktiId);
        if (! $bukti && ! in_array($ra->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true)) {
            $bukti = $ra->buktiDukungs()->findOrFail($buktiId)->only(['mode', 'path', 'nama_asli']);
        }
        abort_unless($bukti && $bukti['mode'] === 'file' && $bukti['path'] && Storage::disk('local')->exists($bukti['path']), 404);

        return Storage::disk('local')->download($bukti['path'], $bukti['nama_asli'], ['Cache-Control' => 'private, no-store']);
    }
}
