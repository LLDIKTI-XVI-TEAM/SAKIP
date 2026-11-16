<?php

namespace App\Http\Controllers\Unit;

use App\Actions\Unit\UpdateUnitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\UpdateUnitRequest;
use Illuminate\Http\RedirectResponse;

class UpdateUnit extends Controller
{
    public function __invoke(UpdateUnitRequest $request, string $id, UpdateUnitAction $action): RedirectResponse
    {
        $nama = $action->handle($request->user(), $id, $request->mutationData());

        return redirect()->route('unit.index')->with('success', "Data unit '{$nama}' berhasil diperbarui.");
    }
}
