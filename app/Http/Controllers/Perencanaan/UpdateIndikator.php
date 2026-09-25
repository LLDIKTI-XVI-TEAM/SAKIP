<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\UpdateIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class UpdateIndikator extends Controller
{
    public function __invoke(UpdateIndikatorRequest $request, IndikatorKinerja $indikator, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $nilaiLama = $indikator->withoutRelations()->toArray();

        DB::transaction(function () use ($indikator, $validated, $actor, $auditLogger, $nilaiLama) {
            $indikator->update([
                'sasaran_strategis_id' => $validated['sasaran_strategis_id'],
                'regulasi_id' => $validated['regulasi_id'] ?? null,
                'kode' => trim($validated['kode']),
                'nama' => trim($validated['nama']),
                'definisi_operasional' => isset($validated['definisi_operasional']) ? trim($validated['definisi_operasional']) : null,
                'satuan' => trim($validated['satuan']),
                'unit_id' => $validated['unit_id'],
                'arah' => $validated['arah'],
                'tipe_perhitungan' => $validated['tipe_perhitungan'],
                'presisi' => $validated['presisi'] ?? 2,
                'desimal_tampilan' => $validated['desimal_tampilan'] ?? 2,
                'wajib_catatan' => (bool) ($validated['wajib_catatan'] ?? false),
                'jenis_agregasi' => $validated['jenis_agregasi'] ?? 'terakhir',
                'is_aktif' => (bool) ($validated['is_aktif'] ?? true),
            ]);

            $nilaiBaru = $indikator->withoutRelations()->toArray();

            $alasan = "Memperbarui indikator kinerja '{$indikator->kode}'.";
            if (($nilaiLama['regulasi_id'] ?? null) !== ($nilaiBaru['regulasi_id'] ?? null)) {
                $alasan .= ' Perubahan regulasi_id: '.($nilaiLama['regulasi_id'] ?? 'kosong').' -> '.($nilaiBaru['regulasi_id'] ?? 'kosong').'.';
            }

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.ubah',
                objekTipe: 'indikator',
                objekId: (string) $indikator->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $nilaiBaru,
                alasan: $alasan,
                dasarIzin: ['permission' => PermissionCodes::INDIKATOR_UPDATE],
            );
        });

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil diperbarui.");
    }
}
