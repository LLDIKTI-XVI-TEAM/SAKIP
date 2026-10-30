<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\BukaKembaliPengukuran as BukaKembaliPengukuranAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\BukaKembaliPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class BukaKembaliPengukuran extends Controller
{
    public function __invoke(BukaKembaliPengukuranRequest $request, string $id, BukaKembaliPengukuranAction $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());

        return back()->with('success', 'Pengukuran dibuka kembali untuk koreksi.');
    }
}
