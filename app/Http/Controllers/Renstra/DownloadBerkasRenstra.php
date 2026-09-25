<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBerkasRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra, Berkas $berkas): StreamedResponse
    {
        Gate::authorize('view', $renstra);
        Gate::authorize('viewAttachment', [$renstra, $berkas]);

        if ($berkas->berkasable_type !== $renstra->getMorphClass() || $berkas->berkasable_id !== $renstra->id) {
            abort(404, 'Lampiran tidak terkait dengan Renstra ini.');
        }

        if ($berkas->mode !== 'file' || empty($berkas->path) || ! Storage::disk('local')->exists($berkas->path)) {
            abort(404, 'File lampiran fisik tidak ditemukan pada storage.');
        }

        return Storage::disk('local')->download($berkas->path, $berkas->nama_asli ?? 'lampiran-renstra');
    }
}
