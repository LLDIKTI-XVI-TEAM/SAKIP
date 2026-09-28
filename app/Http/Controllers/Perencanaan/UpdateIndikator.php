<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\UpdateIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
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

        $result = DB::transaction(function () use ($indikator, $validated, $actor, $auditLogger, $resolver, $request) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            /** @var User|null $lockedActor */
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || ! $lockedActor->is_active) {
                return [
                    'status' => 'denied',
                    'dasarIzin' => [
                        'status' => 'denied',
                        'alasan' => 'Akun pengguna tidak aktif.',
                    ],
                ];
            }

            // 2. Kunci seluruh baris ACL yang menentukan keputusan izin aktor
            DB::table('user_roles')->where('user_id', $lockedActor->id)->sharedLock()->get();
            DB::table('user_permission_granted')->where('user_id', $lockedActor->id)->sharedLock()->get();
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

            $perm = Permission::where('kode', PermissionCodes::INDIKATOR_UPDATE)->sharedLock()->first();
            if ($perm && ! empty($actorRoleIds)) {
                DB::table('role_permissions')->whereIn('role_id', $actorRoleIds)->where('permission_id', $perm->id)->sharedLock()->get();
            }

            // 3. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini (Point 10)
            $currentDecision = $resolver->resolve($lockedActor, PermissionCodes::INDIKATOR_UPDATE);
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // 4. Kunci kedua sasaran secara deterministik dan cegah pemindahan lintas Renstra (Point 6)
            $currentSasaranId = $lockedIndikator->sasaran_strategis_id;
            $targetSasaranId = $validated['sasaran_strategis_id'];
            $sasaranIds = array_values(array_unique([$currentSasaranId, $targetSasaranId]));
            sort($sasaranIds);

            $lockedSasarans = SasaranStrategis::whereIn('id', $sasaranIds)
                ->orderBy('id')
                ->sharedLock()
                ->get()
                ->keyBy('id');

            $currentSasaran = $lockedSasarans->get($currentSasaranId);
            $targetSasaran = $lockedSasarans->get($targetSasaranId);

            if (! $currentSasaran || ! $targetSasaran || $currentSasaran->renstra_id !== $targetSasaran->renstra_id) {
                throw ValidationException::withMessages([
                    'sasaran_strategis_id' => 'Pemindahan indikator ke sasaran strategis di luar Renstra asal tidak diizinkan.',
                ]);
            }

            // 5. Kunci dan periksa ulang status unit tujuan (Point 8)
            /** @var Unit|null $targetUnit */
            $targetUnit = Unit::whereKey($validated['unit_id'])->sharedLock()->first();
            if (! $targetUnit || $targetUnit->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tidak valid atau sudah nonaktif.',
                ]);
            }

            // 6. Validasi alasan perpindahan terhadap baris yang dikunci (Point 5)
            $isUnitChanged = $lockedIndikator->unit_id !== $validated['unit_id'];
            $alasanPindah = null;
            if ($isUnitChanged) {
                $rawAlasanPindah = $validated['alasan_pindah_unit']
                    ?? $validated['alasan']
                    ?? $request->input('alasan_pindah_unit')
                    ?? $request->input('alasan');
                $alasanPindah = is_string($rawAlasanPindah) ? trim($rawAlasanPindah) : '';
                if ($alasanPindah === '' || mb_strlen($alasanPindah) < 10) {
                    throw ValidationException::withMessages([
                        'alasan_pindah_unit' => 'Perpindahan unit penanggung jawab memerlukan alasan minimal 10 karakter.',
                    ]);
                }
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

            // 7. Audit perpindahan unit penanggung jawab jika unit berubah
            if ($isUnitChanged) {
                $oldUnitName = DB::table('unit')->where('id', $nilaiLama['unit_id'])->value('nama') ?? $nilaiLama['unit_id'];
                $newUnitName = $targetUnit->nama ?? $nilaiBaru['unit_id'];

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

            // 8. Audit perubahan data umum jika ada field non-unit yang berubah (Point 9: keluarkan delta unit)
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

                $nilaiLamaUbah = $nilaiLama;
                $nilaiBaruUbah = $nilaiBaru;
                if ($isUnitChanged) {
                    unset($nilaiLamaUbah['unit_id'], $nilaiBaruUbah['unit_id']);
                }

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.ubah',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: $nilaiLamaUbah,
                    nilaiBaru: $nilaiBaruUbah,
                    alasan: $alasan,
                    dasarIzin: $dasarIzin,
                );
            }

            return [
                'status' => 'updated',
                'indikator' => $lockedIndikator,
            ];
        });

        if ($result['status'] === 'denied') {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.ubah_ditolak',
                objekTipe: 'indikator',
                objekId: (string) $indikator->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Pembaruan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang mengubah indikator kinerja.');
        }

        $indikator = $result['indikator'];
        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Indikator kinerja '{$indikator->kode}' berhasil diperbarui.");
    }
}
