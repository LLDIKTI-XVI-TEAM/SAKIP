<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\ChangePengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\ReviewPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class KembalikanPengukuran extends Controller
{
    public function __invoke(ReviewPengukuranRequest $request, string $id, ChangePengukuran $action): RedirectResponse
    {
        $action->handle($request->user(), $id, 'kembalikan', $request->validated());

        return redirect()->route('verifikasi.index')->with('success', 'Pengukuran dikembalikan untuk perbaikan.');
    }
}
