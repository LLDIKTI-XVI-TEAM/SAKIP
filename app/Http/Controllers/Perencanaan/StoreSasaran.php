<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\StoreSasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StoreSasaran extends Controller
{
    public function __invoke(StoreSasaranRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $sasaran = DB::transaction(function () use ($validated, $actor, $auditLogger) {
            $created = SasaranStrategis::create([
                'renstra_id' => $validated['renstra_id'],
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? 0,
            ]);

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat',
                objekTipe: 'sasaran',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah sasaran strategis '{$created->kode}'.",
                dasarIzin: ['permission' => PermissionCodes::SASARAN_CREATE],
            );

            return $created;
        });

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $sasaran->renstra_id])
            ->with('success', "Sasaran strategis '{$sasaran->kode}' berhasil ditambahkan.");
    }
}
