<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\ChangePengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\ReviewPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class VerifyPengukuran extends Controller
{
    public function __invoke(ReviewPengukuranRequest $request, string $id, ChangePengukuran $action): RedirectResponse
    {
        $action->handle($request->user(), $id, 'verifikasi', $request->validated());

        return redirect()->route('verifikasi.show', $id)->with('success', 'Pengukuran berhasil diverifikasi dan siap disahkan.');
    }
}
