<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\KelolaIzinUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\KelolaIzinUnitRequest;
use Illuminate\Http\RedirectResponse;

class KelolaGrant extends Controller
{
    public function __invoke(KelolaIzinUnitRequest $request, KelolaIzinUnitAction $action): RedirectResponse
    {
        $result = $action->handle($request->user(), $request->validated());

        // Tanpa izin tersisa, halaman detail asal sudah tidak ada sehingga kembali ke daftar.
        if ($result['sisa'] === 0) {
            return redirect()->route('akses.grant.index')
                ->with('success', "Seluruh izin unit {$result['target_nama']} pada {$result['unit_nama']} dicabut.");
        }

        return redirect()->back()
            ->with('success', "Izin unit {$result['target_nama']} pada {$result['unit_nama']} diperbarui: {$result['ditambah']} diberikan, {$result['dicabut']} dicabut.");
    }
}
