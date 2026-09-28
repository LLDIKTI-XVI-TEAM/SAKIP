<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\UpdateSasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UpdateSasaran extends Controller
{
    public function __invoke(UpdateSasaranRequest $request, SasaranStrategis $sasaran, AuditLogger $auditLogger, PermissionResolver $resolver): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $dasarIzin = $resolver->resolve($actor, PermissionCodes::SASARAN_UPDATE)->toAuditBasis();

        DB::transaction(function () use ($sasaran, $validated, $actor, $auditLogger, $dasarIzin) {
            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::query()
                ->whereKey($sasaran->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();

            $lockedSasaran->update([
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? $lockedSasaran->urutan,
            ]);

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.ubah',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $lockedSasaran->withoutRelations()->toArray(),
                alasan: "Memperbarui sasaran strategis '{$lockedSasaran->kode}'.",
                dasarIzin: $dasarIzin,
            );
        });

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$sasaran->kode}' berhasil diperbarui.");
    }
}
