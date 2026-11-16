<?php

namespace App\Actions\Laporan;

use App\Models\Berkas;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Laporan\ArsipDokumenResmi;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UnduhArsipDokumenResmi
{
    public function __construct(private readonly ArsipDokumenResmi $arsip) {}

    /** Arsip lama tetap dapat dibaca melalui pemeriksaan akses yang sama dengan penggantinya. */
    public function handle(User $actor, string $id): StreamedResponse
    {
        abort_unless($this->arsip->dapatUnduh($actor->fresh()), 403, ArsipDokumenResmi::AKSES_DIBATASI);
        $berkas = Berkas::whereIn('berkasable_type', ['jadwal_tahunan', 'jadwal_periode'])->where('mode', 'file')->findOrFail($id);
        if ($berkas->berkasable_type === 'jadwal_periode') {
            $periode = PeriodeJadwal::findOrFail($berkas->berkasable_id);
            $this->arsip->induk($periode->jadwal_id, $periode->id);
        } else {
            $this->arsip->induk($berkas->berkasable_id, null);
        }
        abort_unless(Storage::disk('local')->exists($berkas->path), 404);

        return Storage::disk('local')->download($berkas->path, $berkas->nama_asli, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
