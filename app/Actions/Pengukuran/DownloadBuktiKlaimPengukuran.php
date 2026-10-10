<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Storage;

class DownloadBuktiKlaimPengukuran
{
    /**
     * Pilih bukti kegiatan yang dibekukan pada versi pengajuan pengukuran ini; berkas kegiatan live tidak dipakai.
     * Bukti yang tidak ada di versi mana pun, nonfile, atau tanpa berkas fisik dijawab 404. Otorisasi tetap milik adapter.
     *
     * @return array{path: string, name: string|null}
     */
    public function handle(PengukuranKinerja $p, string $buktiId): array
    {
        // Keanggotaan dan metadata berasal dari versi milik pengukuran, bukan berkas kegiatan live.
        $version = $p->versions()->whereJsonContains('snapshot->klaim', [['kegiatan' => ['bukti_dukungs' => [['id' => $buktiId]]]]])
            ->orderByDesc('nomor')->first(['snapshot']);
        $bukti = collect($version?->snapshot['klaim'] ?? [])->flatMap(fn ($claim) => $claim['kegiatan']['bukti_dukungs'])->firstWhere('id', $buktiId);
        abort_unless($bukti && $bukti['mode'] === 'file' && $bukti['path'] && Storage::disk('local')->exists($bukti['path']), 404);

        return ['path' => $bukti['path'], 'name' => $bukti['nama_asli']];
    }
}
