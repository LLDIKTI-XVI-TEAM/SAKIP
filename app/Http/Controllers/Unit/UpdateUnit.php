<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateUnit extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $unit = UnitKerja::findOrFail($id);
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50', Rule::unique('unit_kerjas', 'kode')->ignore($unit->id)],
            'nama' => ['required', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', 'exists:unit_kerjas,id', Rule::notIn([$unit->id])],
            'urutan' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $nilaiLama = $unit->toArray();

        $unit->update([
            'kode' => strtoupper(trim($validated['kode'])),
            'nama' => trim($validated['nama']),
            'singkatan' => $validated['singkatan'] ? trim($validated['singkatan']) : null,
            'parent_id' => $validated['parent_id'] ?? null,
            'urutan' => $validated['urutan'] ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ]);

        AuditLogger::catat(
            tindakan: 'unit.update',
            objekTipe: 'unit',
            objekId: $unit->id,
            nilaiLama: $nilaiLama,
            nilaiBaru: $unit->fresh()->toArray(),
            alasan: $validated['alasan'] ?? 'Pembaruan data master unit organisasi',
            dasarIzin: ['role' => $request->user()->roles->pluck('name')->toArray()]
        );

        return redirect()->route('unit.index')->with('success', "Data unit '{$unit->nama}' berhasil diperbarui.");
    }
}
