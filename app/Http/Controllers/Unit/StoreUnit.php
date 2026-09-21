<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StoreUnit extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        $unit = Unit::create([
            'nama' => trim($validated['nama']),
            'status' => $validated['status'] ?? 'aktif',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unit->nama}' berhasil ditambahkan.");
    }
}
