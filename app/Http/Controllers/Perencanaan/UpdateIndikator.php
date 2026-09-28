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
            $updateData = [
                'sasaran_strategis_id' => $validated['sasaran_strategis_id'],
                'kode' => trim($validated['kode']),
                'nama' => trim($validated['nama']),
                'satuan' => trim($validated['satuan']),
                'unit_id' => $validated['unit_id'],
                'arah' => $validated['arah'],
                'tipe_perhitungan' => $validated['tipe_perhitungan'],
            ];

            if (array_key_exists('definisi_operasional', $validated)) {
                $updateData['definisi_operasional'] = $validated['definisi_operasional'] !== null
                    ? trim($validated['definisi_operasional'])
                    : null;
            }
            if (array_key_exists('regulasi_id', $validated)) {
                $updateData['regulasi_id'] = $validated['regulasi_id'];
            }
            if (array_key_exists('presisi', $validated) && $validated['presisi'] !== null) {
                $updateData['presisi'] = (int) $validated['presisi'];
            }
            if (array_key_exists('desimal_tampilan', $validated) && $validated['desimal_tampilan'] !== null) {
                $updateData['desimal_tampilan'] = (int) $validated['desimal_tampilan'];
            }
            if (array_key_exists('wajib_catatan', $validated) && $validated['wajib_catatan'] !== null) {
                $updateData['wajib_catatan'] = (bool) $validated['wajib_catatan'];
            }
            if (array_key_exists('jenis_agregasi', $validated) && $validated['jenis_agregasi'] !== null) {
                $updateData['jenis_agregasi'] = $validated['jenis_agregasi'];
            }
            if (array_key_exists('is_aktif', $validated) && $validated['is_aktif'] !== null) {
                $updateData['is_aktif'] = (bool) $validated['is_aktif'];
            }

            $indikator->update($updateData);

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
