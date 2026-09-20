<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StoreUnit extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Gate::authorize('create', UnitKerja::class);

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:unit_kerjas,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:unit_kerjas,id'],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit = UnitKerja::create([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama' => trim($validated['nama']),
            'singkatan' => $validated['singkatan'] ? trim($validated['singkatan']) : null,
            'parent_id' => $validated['parent_id'] ?? null,
            'urutan' => $validated['urutan'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        AuditLogger::catat(
            tindakan: 'unit.create',
            objekTipe: 'unit',
            objekId: $unit->id,
            nilaiLama: null,
            nilaiBaru: $unit->toArray(),
            alasan: 'Pembuatan master unit organisasi baru',
            dasarIzin: ['role' => $request->user()->roles->pluck('name')->toArray()]
        );

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unit->nama}' berhasil ditambahkan.");
    }
}
