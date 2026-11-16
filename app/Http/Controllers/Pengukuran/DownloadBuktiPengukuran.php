<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\DownloadBuktiPengukuran as DownloadBuktiPengukuranAction;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBuktiPengukuran extends Controller
{
    public function __invoke(string $id, string $buktiId, DownloadBuktiPengukuranAction $action): StreamedResponse
    {
        $p = PengukuranKinerja::findOrFail($id);
        Gate::authorize('viewEvidence', $p);
        $file = $action->handle($p, $buktiId);

        return Storage::disk('local')->download($file['path'], $file['name'], ['Cache-Control' => 'private, no-store']);
    }
}
