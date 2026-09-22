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

class UpdateUnit extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        /** @var Unit $unit */
        $unit = Unit::findOrFail($id);
        Gate::authorize('update', $unit);

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (trim((string) $value) === '') {
                        $fail('Nama unit organisasi tidak boleh kosong atau hanya berisi spasi.');
                    }
                },
            ],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $user = $request->user();
        $decision = $permissionResolver->resolve($user, 'unit:update');

        $oldValues = [
            'nama' => $unit->nama,
            'status' => $unit->status,
        ];

        DB::transaction(function () use ($unit, $validated, $user, $auditLogger, $decision, $oldValues) {
            $unit->update([
                'nama' => trim($validated['nama']),
                'status' => $validated['status'],
            ]);

            $auditLogger->catat(
                actor: $user,
                tindakan: 'unit.ubah',
                objekTipe: 'unit',
                objekId: (string) $unit->id,
                nilaiLama: $oldValues,
                nilaiBaru: [
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                ],
                alasan: "Perubahan data unit organisasi '{$unit->nama}'.",
                dasarIzin: $decision->toAuditBasis(),
            );
        });

        return redirect()->route('unit.index')->with('success', "Data unit '{$unit->nama}' berhasil diperbarui.");
    }
}
