<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\PindahkanPengukuranKeVersiSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\PindahSnapshotPengukuranRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PindahSnapshotPengukuran extends Controller
{
    public function __invoke(PindahSnapshotPengukuranRequest $request, string $id, PindahkanPengukuranKeVersiSnapshot $action): RedirectResponse
    {
        $pengukuran = $action->handle($request->user(), $id, $request->validated());
        Inertia::flash('success', 'Pengukuran dipindahkan ke versi '.$pengukuran->jadwalSnapshot->nomor_versi.'.');

        return back();
    }
}
