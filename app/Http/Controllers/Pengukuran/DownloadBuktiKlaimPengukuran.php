<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\DownloadBuktiKlaimPengukuran as DownloadBuktiKlaimPengukuranAction;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiKlaimPengukuran extends Controller
{
    public function __invoke(string $id, string $buktiId, DownloadBuktiKlaimPengukuranAction $action): StreamedResponse
    {
        $p = PengukuranKinerja::findOrFail($id);
        Gate::authorize('viewClaimEvidence', $p);
        $file = $action->handle($p, $buktiId);

        return Storage::disk('local')->download($file['path'], $file['name'], ['Cache-Control' => 'private, no-store']);
    }
}
