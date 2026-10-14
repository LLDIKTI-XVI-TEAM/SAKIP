<?php

namespace App\Actions\RencanaAksi;

use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnduhBuktiVersiRencanaAksi
{
    /**
     * Melayani file bukti beku versi pengajuan. Baris current yang masih
     * berlaku dilayani dari path barisnya (perilaku lama tidak berubah);
     * bila baris current sudah tidak berlaku karena diganti atau dihapus
     * (mis. setelah buka-kembali), file beku versi dilayani dari `path`
     * snapshot selama file fisiknya masih ada, dengan otorisasi
     * `viewEvidence` induk yang sama. File fisik yang hilang tetap 404.
     */
    public function handle(User $actor, string $id, string $versi, string $bukti): StreamedResponse
    {
        $header = RencanaAksi::findOrFail($id);
        Gate::forUser($actor)->authorize('viewEvidence', $header);
        $pengajuan = $header->versions()->whereKey($versi)->firstOrFail();
        $beku = collect($pengajuan->snapshot['bukti_dukungs'] ?? [])->first(fn ($row) => is_array($row) && ($row['id'] ?? null) === $bukti && ($row['mode'] ?? null) === 'file');
        abort_unless($beku, 404);
        $berkas = $header->buktiDukungs()->current()->whereKey($bukti)->where('mode', 'file')->first();
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');
        if ($berkas instanceof BuktiDukung) {
            abort_unless(is_string($berkas->path) && $disk->exists($berkas->path), 404);

            return $disk->download($berkas->path, $beku['nama_asli'] ?? 'bukti', ['Cache-Control' => 'private, no-store']);
        }
        $jalurBeku = $beku['path'] ?? null;
        abort_unless(is_string($jalurBeku) && $jalurBeku !== '' && $disk->exists($jalurBeku), 404);

        return $disk->download($jalurBeku, $beku['nama_asli'] ?? 'bukti', ['Cache-Control' => 'private, no-store']);
    }
}
