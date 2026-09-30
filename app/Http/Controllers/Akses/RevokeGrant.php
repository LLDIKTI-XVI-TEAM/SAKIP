<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\RevokeUnitGrantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\RevokeUnitGrantRequest;
use Illuminate\Http\RedirectResponse;

class RevokeGrant extends Controller
{
    public function __invoke(RevokeUnitGrantRequest $request, string $id, RevokeUnitGrantAction $action): RedirectResponse
    {
        $result = $action->handle($request->user(), $id, $request->validated('alasan'));

        return redirect()->route('akses.grant.index')
            ->with('success', "Izin '{$result['permName']}' untuk pengguna {$result['userName']} berhasil dicabut.");
    }
}
