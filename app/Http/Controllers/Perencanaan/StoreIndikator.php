<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\StoreIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\RoleCatalog;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StoreIndikator extends Controller
{
    public function __invoke(StoreIndikatorRequest $request, AuditLogger $auditLogger, PermissionResolver $resolver): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();
        $dasarIzin = $resolver->resolve($actor, PermissionCodes::INDIKATOR_CREATE)->toAuditBasis();

        $indikator = DB::transaction(function () use ($validated, $actor, $auditLogger, $dasarIzin) {
            $createdRole = $actor->roles()->where('roles.aktif', true)->orderBy('roles.urutan')->value('roles.kode') ?? 'perencanaan';
            if (! RoleCatalog::contains($createdRole)) {
                $createdRole = 'perencanaan';
            }

            $created = IndikatorKinerja::create([
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
                'created_by_role' => $createdRole,
            ]);

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.buat',
                objekTipe: 'indikator',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah indikator kinerja '{$created->kode} - {$created->nama}'.",
                dasarIzin: $dasarIzin,
            );

            return $created;
        });

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil ditambahkan.");
    }
}
