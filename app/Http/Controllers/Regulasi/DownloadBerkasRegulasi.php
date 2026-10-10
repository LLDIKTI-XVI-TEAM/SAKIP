<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\DownloadBerkasRegulasi as DownloadBerkasRegulasiAction;
use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBerkasRegulasi extends Controller
{
    public function __invoke(Regulasi $regulasi, Berkas $berkas, DownloadBerkasRegulasiAction $action): StreamedResponse
    {
        Gate::authorize('view', $regulasi);
        $file = $action->handle($regulasi, $berkas);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }
}
