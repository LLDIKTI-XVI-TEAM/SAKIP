<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\ChangePengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\SavePengukuranRequest;
use Illuminate\Http\RedirectResponse;

class UpdatePengukuran extends Controller
{
    public function __invoke(SavePengukuranRequest $request, string $id, ChangePengukuran $action): RedirectResponse
    {
        $data = $request->validated();
        $action->handle($request->user(), $id, $data['action'], $data);

        return redirect()->route('pengukuran.index')->with('success', $data['action'] === 'ajukan' ? 'Pengukuran berhasil diajukan untuk verifikasi.' : 'Draf pengukuran berhasil disimpan.');
    }
}
