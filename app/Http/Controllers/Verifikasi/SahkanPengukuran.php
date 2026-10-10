<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\ApprovePengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\ReviewPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class SahkanPengukuran extends Controller
{
    public function __invoke(ReviewPengukuranRequest $request, string $id, ApprovePengukuran $action): RedirectResponse
    {
        $action->handle($request->user(), $id, $request->validated());

        return redirect()->route('verifikasi.index')->with('success', 'Pengukuran disahkan dan snapshot pengajuan dipertahankan.');
    }
}
