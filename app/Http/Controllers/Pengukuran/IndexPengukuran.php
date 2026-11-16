<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\IndexPengukuran as IndexPengukuranAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengukuran extends Controller
{
    public function __invoke(Request $request, IndexPengukuranAction $action): Response
    {
        return Inertia::render('Pengukuran/Index', $action->handle($request->user(), $request->query()));
    }
}
