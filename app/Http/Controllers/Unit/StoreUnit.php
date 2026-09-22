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

class StoreUnit extends Controller
{
    public function __invoke(Request $request, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);

        $user = $request->user();
        $decision = $permissionResolver->resolve($user, 'unit:create');

        $unit = DB::transaction(function () use ($validated, $user, $auditLogger, $decision) {
            $unit = Unit::create([
                'nama' => trim($validated['nama']),
                'status' => $validated['status'] ?? 'aktif',
                'created_by' => $user->id,
            ]);

            $auditLogger->catat(
                actor: $user,
                tindakan: 'unit.tambah',
                objekTipe: 'unit',
                objekId: (string) $unit->id,
                nilaiLama: null,
                nilaiBaru: [
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                    'created_by' => $unit->created_by,
                ],
                alasan: "Penambahan unit organisasi '{$unit->nama}'.",
                dasarIzin: $decision->toAuditBasis(),
            );

            return $unit;
        });

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unit->nama}' berhasil ditambahkan.");
    }
}
