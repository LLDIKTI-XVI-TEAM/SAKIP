<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\StoreIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\RoleCatalog;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreIndikator extends Controller
{
    public function __invoke(StoreIndikatorRequest $request, AuditLogger $auditLogger, PermissionResolver $resolver): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $actor, $auditLogger, $resolver) {
            // Kunci user dan role aktif aktor untuk mencegah race condition pencabutan peran / izin
            User::whereKey($actor->id)->sharedLock()->first();

            $actorRoleIds = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $actor->id)
                ->where('roles.aktif', true)
                ->pluck('roles.id')
                ->all();
            sort($actorRoleIds);
            if (! empty($actorRoleIds)) {
                Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
            }

            // Evaluasi ulang keputusan izin di dalam transaksi yang terkunci
            $currentDecision = $resolver->resolve($actor, PermissionCodes::INDIKATOR_CREATE);
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // Ambil role aktif aktor yang memberikan izin indikator:create berdasarkan resolusi Q32 terkini
            $grantingRoleIds = $currentDecision->basis['sumber_allow']['roles'] ?? [];
            $createdRole = null;
            if (! empty($grantingRoleIds)) {
                $createdRole = DB::table('roles')
                    ->whereIn('id', (array) $grantingRoleIds)
                    ->where('aktif', true)
                    ->whereIn('kode', RoleCatalog::codes())
                    ->orderBy('urutan')
                    ->value('kode');
            }

            if (! $createdRole) {
                // Fallback ke role aktif tertinggi aktor jika izin diperoleh melalui grant/mekanisme lain
                $createdRole = $actor->roles()
                    ->where('roles.aktif', true)
                    ->whereIn('roles.kode', RoleCatalog::codes())
                    ->orderBy('roles.urutan')
                    ->value('roles.kode');
            }

            if (! $createdRole || ! in_array($createdRole, IndikatorKinerja::creatableRoles(), true)) {
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
                dasarIzin: $currentDecision->toAuditBasis(),
            );

            return [
                'status' => 'created',
                'indikator' => $created,
            ];
        });

        if ($result['status'] === 'denied') {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.buat_ditolak',
                objekTipe: 'indikator',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Pembuatan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang menambah indikator kinerja.');
        }

        $indikator = $result['indikator'];
        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil ditambahkan.");
    }
}
