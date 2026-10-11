<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnduhBuktiVersiRencanaAksi
{
    public function handle(User $actor, string $id, string $versi, string $bukti): StreamedResponse
    {
        $header = RencanaAksi::findOrFail($id);
        Gate::forUser($actor)->authorize('viewEvidence', $header);
        $pengajuan = $header->versions()->whereKey($versi)->firstOrFail();
        $beku = collect($pengajuan->snapshot['bukti_dukungs'] ?? [])->first(fn ($row) => is_array($row) && ($row['id'] ?? null) === $bukti && ($row['mode'] ?? null) === 'file');
        abort_unless($beku, 404);
        // Bukti historis boleh soft-deleted; path berasal dari berkas milik induk, bukan JSON kiriman.
        $berkas = $header->buktiDukungs()->whereKey($bukti)->where('mode', 'file')->first();
        abort_unless($berkas && is_string($berkas->path) && Storage::disk('local')->exists($berkas->path), 404);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        return $disk->download($berkas->path, $beku['nama_asli'] ?? 'bukti', ['Cache-Control' => 'private, no-store']);
    }
}
