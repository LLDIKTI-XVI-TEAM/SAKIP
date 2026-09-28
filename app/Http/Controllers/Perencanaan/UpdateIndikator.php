<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\UpdateIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateIndikator extends Controller
{
    public function __invoke(
        UpdateIndikatorRequest $request,
        IndikatorKinerja $indikator,
        AuditLogger $auditLogger,
        PermissionResolver $resolver
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $dasarIzin = $resolver->resolve($actor, PermissionCodes::INDIKATOR_UPDATE)->toAuditBasis();

        DB::transaction(function () use ($indikator, $validated, $actor, $auditLogger, $dasarIzin, $request) {
            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // Cegah pemindahan indikator lintas Renstra
            $currentRenstraId = DB::table('sasaran_strategis')
                ->where('id', $lockedIndikator->sasaran_strategis_id)
                ->value('renstra_id');
            $targetRenstraId = DB::table('sasaran_strategis')
                ->where('id', $validated['sasaran_strategis_id'])
                ->value('renstra_id');

            if ($currentRenstraId !== $targetRenstraId) {
                throw ValidationException::withMessages([
                    'sasaran_strategis_id' => 'Pemindahan indikator ke sasaran strategis di luar Renstra asal tidak diizinkan.',
                ]);
            }

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

            $lockedIndikator->update($updateData);

            $nilaiBaru = $lockedIndikator->withoutRelations()->toArray();

            // 1. Audit eksplisit bila terjadi perpindahan unit penanggung jawab
            $isUnitChanged = ($nilaiLama['unit_id'] ?? null) !== ($nilaiBaru['unit_id'] ?? null);
            if ($isUnitChanged) {
                $oldUnitName = DB::table('unit')->where('id', $nilaiLama['unit_id'])->value('nama') ?? $nilaiLama['unit_id'];
                $newUnitName = DB::table('unit')->where('id', $nilaiBaru['unit_id'])->value('nama') ?? $nilaiBaru['unit_id'];
                $alasanPindah = $validated['alasan_pindah_unit']
                    ?? $validated['alasan']
                    ?? $request->input('alasan')
                    ?? "Perpindahan unit penanggung jawab dari '{$oldUnitName}' ke '{$newUnitName}'.";

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.pindah_unit',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: [
                        'unit_id' => $nilaiLama['unit_id'],
                        'unit_nama' => $oldUnitName,
                    ],
                    nilaiBaru: [
                        'unit_id' => $nilaiBaru['unit_id'],
                        'unit_nama' => $newUnitName,
                    ],
                    alasan: $alasanPindah,
                    dasarIzin: $dasarIzin,
                );
            }

            // 2. Audit perubahan data umum jika ada field non-unit yang berubah
            $generalFields = ['sasaran_strategis_id', 'regulasi_id', 'kode', 'nama', 'definisi_operasional', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'wajib_catatan', 'jenis_agregasi', 'is_aktif'];
            $hasGeneralChanges = false;
            foreach ($generalFields as $field) {
                if (($nilaiLama[$field] ?? null) !== ($nilaiBaru[$field] ?? null)) {
                    $hasGeneralChanges = true;
                    break;
                }
            }

            if ($hasGeneralChanges) {
                $alasan = "Memperbarui indikator kinerja '{$lockedIndikator->kode}'.";
                if (($nilaiLama['regulasi_id'] ?? null) !== ($nilaiBaru['regulasi_id'] ?? null)) {
                    $alasan .= ' Perubahan regulasi_id: '.($nilaiLama['regulasi_id'] ?? 'kosong').' -> '.($nilaiBaru['regulasi_id'] ?? 'kosong').'.';
                }

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.ubah',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: $alasan,
                    dasarIzin: $dasarIzin,
                );
            }
        });

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil diperbarui.");
    }
}
