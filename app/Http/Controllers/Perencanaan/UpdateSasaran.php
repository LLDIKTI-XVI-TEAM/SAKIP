<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\UpdateSasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UpdateSasaran extends Controller
{
    public function __invoke(UpdateSasaranRequest $request, SasaranStrategis $sasaran, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $nilaiLama = $sasaran->withoutRelations()->toArray();

        DB::transaction(function () use ($sasaran, $validated, $actor, $auditLogger, $nilaiLama) {
            $sasaran->update([
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? $sasaran->urutan,
            ]);

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.ubah',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $sasaran->withoutRelations()->toArray(),
                alasan: "Memperbarui sasaran strategis '{$sasaran->kode}'.",
                dasarIzin: ['permission' => PermissionCodes::SASARAN_UPDATE],
            );
        });

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$sasaran->kode}' berhasil diperbarui.");
    }
}
