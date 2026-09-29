<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateIndikator
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
    ) {}

    /**
     * Memperbarui satu Indikator beserta auditnya.
     *
     * Seperti StoreIndikator: izin dievaluasi ulang di dalam transaksi
     * terkunci, `nilaiLama` diambil dari baris terkunci (anti lost-update),
     * kedua Sasaran (lama dan tujuan) dikunci deterministik terurut agar
     * pemindahan lintas Renstra tertolak sebelum mutasi (Q6/ADR 0002), dan
     * pindah unit tujuan wajib aktif + alasan ≥10 karakter + audit
     * `indikator.pindah_unit` terpisah (Q2). Kolom `jenis_agregasi` beku MVP
     * dan tidak pernah ditulis dari request (Q1/Q5).
     *
     * @param  array{alasan_pindah_unit?: mixed, alasan?: mixed}  $input  Input mentah untuk fallback alasan pindah.
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $validated, array $input): IndikatorKinerja
    {
        $result = DB::transaction(function () use ($indikator, $validated, $actor, $input) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_UPDATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembaruan indikator kinerja ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembaruan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
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

            // 3. Kunci kedua sasaran secara deterministik dan cegah pemindahan lintas Renstra
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

            // 4. Kunci dan periksa status unit: unit lama tetap diizinkan walaupun nonaktif untuk edit biasa,
            // namun perpindahan ke unit baru wajib berstatus aktif
            $isUnitChanged = $lockedIndikator->unit_id !== $validated['unit_id'];

            /** @var Unit|null $targetUnit */
            $targetUnit = Unit::whereKey($validated['unit_id'])->sharedLock()->first();
            if (! $targetUnit) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tidak valid.',
                ]);
            }

            if ($isUnitChanged && $targetUnit->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tujuan tidak valid atau sudah nonaktif.',
                ]);
            }

            // 5. Validasi alasan perpindahan terhadap baris yang dikunci
            $alasanPindah = null;
            if ($isUnitChanged) {
                $rawAlasanPindah = $validated['alasan_pindah_unit']
                    ?? $validated['alasan']
                    ?? $input['alasan_pindah_unit']
                    ?? $input['alasan'];
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
            if (array_key_exists('is_aktif', $validated) && $validated['is_aktif'] !== null) {
                $updateData['is_aktif'] = (bool) $validated['is_aktif'];
            }

            $lockedIndikator->update($updateData);

            $nilaiBaru = $lockedIndikator->withoutRelations()->toArray();

            // 6. Audit perpindahan unit penanggung jawab jika unit berubah
            if ($isUnitChanged) {
                $oldUnitName = DB::table('unit')->where('id', $nilaiLama['unit_id'])->value('nama') ?? $nilaiLama['unit_id'];
                $newUnitName = $targetUnit->nama ?? $nilaiBaru['unit_id'];

                $this->auditLogger->catat(
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

            // 7. Audit perubahan data umum jika ada field non-unit yang berubah (delta unit dikeluarkan)
            $generalFields = ['sasaran_strategis_id', 'regulasi_id', 'kode', 'nama', 'definisi_operasional', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'wajib_catatan', 'is_aktif'];
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

                $this->auditLogger->catat(
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
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.ubah_ditolak',
                objekTipe: 'indikator',
                objekId: (string) $indikator->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pembaruan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang mengubah indikator kinerja.');
        }

        return $result['indikator'];
    }
}
