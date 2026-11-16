<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\CreateUnitGrantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreUnitGrantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class StoreGrant extends Controller
{
    public function __invoke(StoreUnitGrantRequest $request, CreateUnitGrantAction $action): RedirectResponse
    {
        $result = $action->handle($request->user(), $request->validated());

        // Kembali ke filter daftar asal; isian dari halaman PJ dan nomor halaman tidak dibawa.
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $asal);

        return redirect()->route('akses.grant.index', array_filter(Arr::only($asal, ['search', 'unit_id']), 'is_string'))
            ->with('success', "{$result['jumlah']} izin unit pada {$result['unit_nama']} berhasil diberikan kepada {$result['target_nama']}.");
    }
}
