<?php

namespace App\Http\Controllers\Unit;

use App\Actions\Unit\CreateUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use Illuminate\Http\RedirectResponse;

class StoreUnit extends Controller
{
    public function __invoke(StoreUnitRequest $request, CreateUnitAction $action): RedirectResponse
    {
        $unit = $action->handle($request->user(), $request->validated());

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unit->nama}' berhasil ditambahkan.");
    }
}
