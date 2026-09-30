<?php

namespace App\Http\Controllers\Unit;

use App\Actions\Unit\DeleteUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\DestroyUnitRequest;
use Illuminate\Http\RedirectResponse;

class DestroyUnit extends Controller
{
    public function __invoke(DestroyUnitRequest $request, string $id, DeleteUnitAction $action): RedirectResponse
    {
        $nama = $action->handle($request->user(), $id, $request->validated('alasan'), $request->initialDecision());

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$nama}' berhasil dihapus.");
    }
}
