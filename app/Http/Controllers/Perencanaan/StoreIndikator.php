<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\StoreIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\RoleCatalog;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreIndikator extends Controller
{
    public function __invoke(StoreIndikatorRequest $request, AuditLogger $auditLogger, PermissionResolver $resolver): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated, $actor, $auditLogger, $resolver) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            /** @var User|null $lockedActor */
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                $inactiveDecision = $resolver->resolve($lockedActor ?? $actor, PermissionCodes::INDIKATOR_CREATE);

                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $inactiveDecision->toAuditBasis(),
                ];
            }

            // 2. Kunci seluruh baris ACL yang menentukan keputusan izin aktor
            // Hanya kunci grant global (whereNull unit_id) agar tidak deadlock dengan pencabutan grant unit pada RevokeGrant
            DB::table('user_roles')->where('user_id', $lockedActor->id)->sharedLock()->get();
            DB::table('user_permission_granted')->where('user_id', $lockedActor->id)->whereNull('unit_id')->sharedLock()->get();
            DB::table('user_permission_denied')->where('user_id', $lockedActor->id)->sharedLock()->get();

            $actorRoleIds = DB::table('user_roles')
                ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                ->where('user_roles.user_id', $lockedActor->id)
                ->where('roles.aktif', true)
                ->pluck('roles.id')
                ->all();
            sort($actorRoleIds);
            if (! empty($actorRoleIds)) {
                Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
            }

            $perm = Permission::where('kode', PermissionCodes::INDIKATOR_CREATE)->sharedLock()->first();
            if ($perm && ! empty($actorRoleIds)) {
                DB::table('role_permissions')->whereIn('role_id', $actorRoleIds)->where('permission_id', $perm->id)->sharedLock()->get();
            }

            // 3. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            $currentDecision = $resolver->resolve($lockedActor, PermissionCodes::INDIKATOR_CREATE);
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 4. Ambil role aktif aktor yang memberikan izin indikator:create berdasarkan resolusi Q32
            // Fail-closed: jangan mengarang role bila izin diperoleh hanya dari direct grant tanpa role pemberi izin
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

            if (! $createdRole || ! in_array($createdRole, IndikatorKinerja::creatableRoles(), true)) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan indikator kinerja ditolak karena wewenang pembuatan tidak bersumber dari peran resmi yang sah untuk provenance.',
                    'dasarIzin' => array_merge($currentDecision->toAuditBasis(), [
                        'penolakan_provenance' => 'Izin pembuatan indikator tidak bersumber dari peran resmi yang sah untuk provenance.',
                    ]),
                ];
            }

            // 5. Kunci dan periksa ulang status unit tujuan di dalam transaksi
            /** @var Unit|null $targetUnit */
            $targetUnit = Unit::whereKey($validated['unit_id'])->sharedLock()->first();
            if (! $targetUnit || $targetUnit->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tidak valid atau sudah nonaktif.',
                ]);
            }

            // 6. Kunci sasaran strategis induk
            $sasaran = SasaranStrategis::whereKey($validated['sasaran_strategis_id'])->sharedLock()->first();
            if (! $sasaran) {
                throw ValidationException::withMessages([
                    'sasaran_strategis_id' => 'Sasaran strategis yang dipilih tidak valid.',
                ]);
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
                alasan: $result['alasan'] ?? 'Pembuatan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
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
