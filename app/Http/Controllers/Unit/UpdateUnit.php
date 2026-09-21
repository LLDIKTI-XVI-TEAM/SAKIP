<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UpdateUnit extends Controller
{
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $unit = Unit::findOrFail($id);
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $unit->update([
            'nama' => trim($validated['nama']),
            'status' => $validated['status'],
        ]);

        return redirect()->route('unit.index')->with('success', "Data unit '{$unit->nama}' berhasil diperbarui.");
    }
}
