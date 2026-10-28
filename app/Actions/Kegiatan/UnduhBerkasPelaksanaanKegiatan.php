<?php

namespace App\Actions\Kegiatan;

use App\Models\BuktiDukung;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Kegiatan\GerbangBerkasKegiatan;
use App\Services\Kegiatan\PersyaratanPerencanaanKegiatan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class UnduhBerkasPelaksanaanKegiatan
{
    /** Bukti privat harus milik kegiatan, masih berlaku, dan bertahap pelaksanaan; deny baca berkas tetap menang. */
    public function handle(User $actor, Kegiatan $kegiatan, string $berkasId, GerbangBerkasKegiatan $gerbang): BuktiDukung
    {
        Gate::forUser($actor)->authorize('view', $kegiatan);
        abort_if($gerbang->bacaTertutup($actor, $kegiatan), 403);
        $bukti = PersyaratanPerencanaanKegiatan::buktiBerlaku((string) $kegiatan->id)->whereKey($berkasId)->where('mode', 'file')
            ->whereHas('jenisBerkas', fn ($query) => $query->where('tahap', PersyaratanPerencanaanKegiatan::TAHAP_PELAKSANAAN))->first();
        abort_unless($bukti && is_string($bukti->path) && Storage::disk('local')->exists($bukti->path), 404);

        return $bukti;
    }
}
