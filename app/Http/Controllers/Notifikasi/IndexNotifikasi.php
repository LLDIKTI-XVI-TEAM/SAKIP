<?php

namespace App\Http\Controllers\Notifikasi;

use App\Actions\Notifikasi\DaftarNotifikasi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexNotifikasi extends Controller
{
    public function __invoke(Request $request, DaftarNotifikasi $action): Response
    {
        return Inertia::render('Notifikasi/Index', $action->handle($request->user()));
    }
}
