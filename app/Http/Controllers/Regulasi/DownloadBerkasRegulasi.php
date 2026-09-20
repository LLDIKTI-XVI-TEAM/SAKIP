<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBerkasRegulasi extends Controller
{
    public function __invoke(Regulasi $regulasi, Berkas $berkas): StreamedResponse
    {
        Gate::authorize('view', $regulasi);

        abort_unless(
            $berkas->berkasable_type === $regulasi->getMorphClass()
                && $berkas->berkasable_id === $regulasi->id
                && $berkas->mode === 'file'
                && is_string($berkas->path),
            404,
        );

        abort_unless(Storage::disk('local')->exists($berkas->path), 404);

        return Storage::disk('local')->download(
            $berkas->path,
            $berkas->nama_asli ?: 'lampiran-regulasi',
        );
    }
}
