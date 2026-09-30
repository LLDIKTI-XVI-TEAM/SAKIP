<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\CreateUnitGrantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreUnitGrantRequest;
use Illuminate\Http\RedirectResponse;

class StoreGrant extends Controller
{
    public function __invoke(StoreUnitGrantRequest $request, CreateUnitGrantAction $action): RedirectResponse
    {
        $result = $action->handle($request->user(), $request->validated());

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$result['permission_kode']}' pada unit '{$result['unit_nama']}' berhasil diberikan kepada {$result['target_nama']}.");
    }
}
