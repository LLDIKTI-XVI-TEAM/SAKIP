<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\IndexPengukuranDisahkan as IndexPengukuranDisahkanAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengukuranDisahkan extends Controller
{
    public function __invoke(Request $request, IndexPengukuranDisahkanAction $action): Response
    {
        // Halaman ini selalu menampilkan status `disahkan`; status bukan input pengguna.
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'renstra_id' => ['nullable', 'uuid'], 'tahun' => ['nullable', 'integer', 'between:1,9999'],
            'periode_id' => ['nullable', 'uuid'], 'unit_id' => ['nullable', 'uuid'],
        ]);

        return Inertia::render('Verifikasi/Disahkan', $action->handle($request->user(), $filters));
    }

    public function pilihan(Request $request, IndexPengukuranDisahkanAction $action): JsonResponse
    {
        $input = $request->validate(['jenis' => ['required', 'in:renstra,periode,unit'],
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json($action->pilihan($request->user(), $input));
    }
}
