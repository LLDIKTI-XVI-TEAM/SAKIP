<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DestroyUnit extends Controller
{
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $unit = Unit::findOrFail($id);
        Gate::authorize('delete', $unit);

        if (! $unit->isDeletable()) {
            return redirect()->route('unit.index')->with('error', 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, atau izin terkait.');
        }

        $unitNama = $unit->nama;
        $unit->delete();

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unitNama}' berhasil dihapus.");
    }
}
