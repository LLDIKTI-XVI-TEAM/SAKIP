<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DestroyUnit extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $unit = UnitKerja::findOrFail($id);
        Gate::authorize('delete', $unit);

        if (! $unit->isDeletable()) {
            return redirect()->route('unit.index')->with('error', 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, penugasan, atau pegawai.');
        }

        $validated = $request->validate([
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $unitId = $unit->id;
        $unitNama = $unit->nama;
        $nilaiLama = $unit->toArray();

        $unit->delete();

        AuditLogger::catat(
            tindakan: 'unit.delete',
            objekTipe: 'unit',
            objekId: $unitId,
            nilaiLama: $nilaiLama,
            nilaiBaru: null,
            alasan: $validated['alasan'] ?? 'Penghapusan unit organisasi kosong',
            dasarIzin: ['role' => $request->user()->roles->pluck('name')->toArray()]
        );

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unitNama}' berhasil dihapus secara permanen.");
    }
}
