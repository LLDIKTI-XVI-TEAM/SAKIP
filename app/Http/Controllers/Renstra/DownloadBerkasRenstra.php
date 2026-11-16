<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\DownloadBerkasRenstra as DownloadBerkasRenstraAction;
use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Renstra;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBerkasRenstra extends Controller
{
    public function __invoke(Renstra $renstra, Berkas $berkas, DownloadBerkasRenstraAction $action): StreamedResponse
    {
        Gate::authorize('view', $renstra);
        Gate::authorize('viewAttachment', [$renstra, $berkas]);
        $file = $action->handle($renstra, $berkas);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }
}
