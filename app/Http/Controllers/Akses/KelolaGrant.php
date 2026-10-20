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
        $data = $request->validated();
        $result = $action->handle($request->user(), $data);

        if ($result['sisa'] === 0) {
            // Halaman detail asal sudah tidak ada; dari daftar kembali ke halaman asal agar filter tetap.
            $detail = route('akses.grant.show', ['user' => $data['user_id'], 'unit' => $data['unit_id']], false);
            $tujuan = parse_url(url()->previous(), PHP_URL_PATH) === $detail ? redirect()->route('akses.grant.index') : redirect()->back();

            return $tujuan->with('success', "Seluruh izin unit {$result['target_nama']} pada {$result['unit_nama']} dicabut.");
        }

        return redirect()->back()
            ->with('success', "Izin unit {$result['target_nama']} pada {$result['unit_nama']} diperbarui: {$result['ditambah']} diberikan, {$result['dicabut']} dicabut.");
    }
}
