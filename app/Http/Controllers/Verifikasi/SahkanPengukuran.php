<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\ChangePengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\ReviewPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class SahkanPengukuran extends Controller
{
    public function __invoke(ReviewPengukuranRequest $request, string $id, ChangePengukuran $action): RedirectResponse
    {
        $action->handle($request->user(), $id, 'sahkan', $request->validated());

        return redirect()->route('verifikasi.index')->with('success', 'Pengukuran disahkan dan snapshot pengajuan dipertahankan.');
    }
}
