<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\SaveDraftPengukuran;
use App\Actions\Pengukuran\SubmitPengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\SavePengukuranRequest;
use Illuminate\Http\RedirectResponse;

class UpdatePengukuran extends Controller
{
    public function __invoke(SavePengukuranRequest $request, string $id, SaveDraftPengukuran $draft, SubmitPengukuran $submit): RedirectResponse
    {
        $data = $request->validated();
        $submitting = $data['action'] === 'ajukan';
        ($submitting ? $submit : $draft)->handle($request->user(), $id, $data);

        return redirect()->route('pengukuran.index')->with('success', $submitting ? 'Pengukuran berhasil diajukan untuk verifikasi.' : 'Draf pengukuran berhasil disimpan.');
    }
}
