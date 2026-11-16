<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Storage;

class DownloadBuktiPengukuran
{
    /**
     * Pilih bukti pengukuran dari metadata beku versi pengajuan bila ada; bukti live hanya untuk pengukuran yang belum diajukan.
     * Bukti nonfile, asing, atau tanpa berkas fisik dijawab 404. Otorisasi `viewEvidence` tetap milik adapter.
     *
     * @return array{path: string, name: string|null}
     */
    public function handle(PengukuranKinerja $p, string $buktiId): array
    {
        // URL bukti historis tetap memakai metadata beku meski versi terbaru memilih pengganti.
        $version = $p->versions()->whereJsonContains('snapshot->bukti_dukungs', [['id' => $buktiId]])->orderByDesc('nomor')->first(['snapshot']);
        $bukti = collect($version?->snapshot['bukti_dukungs'] ?? [])->firstWhere('id', $buktiId);
        if (! $bukti && ! in_array($p->status_alur, ['diajukan', 'diverifikasi', 'disahkan'], true)) {
            $bukti = $p->buktiDukungs()->findOrFail($buktiId)->only(['mode', 'path', 'nama_asli']);
        }
        abort_unless($bukti && $bukti['mode'] === 'file' && $bukti['path'] && Storage::disk('local')->exists($bukti['path']), 404);

        return ['path' => $bukti['path'], 'name' => $bukti['nama_asli']];
    }
}
