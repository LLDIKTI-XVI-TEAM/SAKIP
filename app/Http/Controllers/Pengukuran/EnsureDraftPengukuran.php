<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\EnsureDraftPengukuran as EnsureDraftPengukuranAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\EnsureDraftPengukuranRequest;
use Illuminate\Http\RedirectResponse;

class EnsureDraftPengukuran extends Controller
{
    public function __invoke(EnsureDraftPengukuranRequest $request, EnsureDraftPengukuranAction $action): RedirectResponse
    {
        $data = $request->validated();

        return redirect()->route('pengukuran.edit', $action->handle($request->user(), $data['jadwal_snapshot_id'], $data['periode_id'])->id);
    }
}
