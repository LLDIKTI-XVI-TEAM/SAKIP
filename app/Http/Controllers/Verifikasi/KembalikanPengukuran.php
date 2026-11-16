<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\ReturnPengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\ReviewPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class KembalikanPengukuran extends Controller
{
    public function __invoke(ReviewPengukuranRequest $request, string $id, ReturnPengukuran $action): RedirectResponse
    {
        $pengukuran = $action->handle($request->user(), $id, $request->validated());

        return redirect()->route('verifikasi.index', ['status' => $pengukuran->getPrevious()['status_alur']])->with('success', 'Pengukuran dikembalikan untuk perbaikan.');
    }
}
