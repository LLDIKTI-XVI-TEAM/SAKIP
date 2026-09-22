<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DestroyUnit extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        /** @var Unit $unit */
        $unit = Unit::findOrFail($id);
        Gate::authorize('delete', $unit);

        if (! $unit->isDeletable()) {
            return redirect()->route('unit.index')->with('error', 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, atau izin terkait.');
        }

        $user = $request->user();
        $decision = $permissionResolver->resolve($user, 'unit:delete');

        $unitId = $unit->id;
        $unitNama = $unit->nama;
        $oldValues = [
            'id' => $unit->id,
            'nama' => $unit->nama,
            'status' => $unit->status,
            'created_by' => $unit->created_by,
        ];

        DB::transaction(function () use ($unit, $unitId, $unitNama, $user, $auditLogger, $decision, $oldValues) {
            $unit->delete();

            $auditLogger->catat(
                actor: $user,
                tindakan: 'unit.hapus',
                objekTipe: 'unit',
                objekId: (string) $unitId,
                nilaiLama: $oldValues,
                nilaiBaru: null,
                alasan: "Penghapusan permanen unit organisasi '{$unitNama}'.",
                dasarIzin: $decision->toAuditBasis(),
            );
        });

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unitNama}' berhasil dihapus.");
    }
}
