<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\PreviewPengukuran as Preview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\PreviewPengukuranRequest;
use Illuminate\Http\JsonResponse;

class PreviewPengukuran extends Controller
{
    public function __invoke(PreviewPengukuranRequest $request, string $id, Preview $preview): JsonResponse
    {
        return response()->json($preview->handle($request->user(), $id, $request->validated()))->header('Cache-Control', 'no-store');
    }
}
