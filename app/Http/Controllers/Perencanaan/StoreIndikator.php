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
        $decision = $resolver->resolve($actor, PermissionCodes::INDIKATOR_CREATE);
        if (! $decision->allowed) {
            abort(403);
        }
        $dasarIzin = $decision->toAuditBasis();

        $indikator = DB::transaction(function () use ($validated, $actor, $auditLogger, $dasarIzin) {
            // Ambil role aktif aktor yang memiliki izin indikator:create berdasarkan resolusi Q32
            $createdRole = DB::table('roles')
                ->join('user_roles', 'user_roles.role_id', '=', 'roles.id')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('user_roles.user_id', $actor->id)
                ->where('roles.aktif', true)
                ->where('permissions.kode', PermissionCodes::INDIKATOR_CREATE)
                ->whereIn('roles.kode', RoleCatalog::codes())
                ->orderBy('roles.urutan')
                ->value('roles.kode');

            if (! $createdRole) {
                // Fallback ke role aktif tertinggi aktor jika izin diberikan melalui grant/mekanisme lain
                $createdRole = $actor->roles()
                    ->where('roles.aktif', true)
                    ->whereIn('roles.kode', RoleCatalog::codes())
                    ->orderBy('roles.urutan')
                    ->value('roles.kode');
            }

            if (! $createdRole || ! RoleCatalog::contains($createdRole)) {
                throw new \LogicException('Tidak dapat menentukan role otoritas yang sah dari aktor untuk pembuatan indikator.');
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
