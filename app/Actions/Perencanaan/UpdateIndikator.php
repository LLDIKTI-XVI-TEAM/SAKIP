<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     * dan kedua Sasaran (lama dan tujuan) dikunci deterministik terurut agar
     * pemindahan lintas Renstra tertolak sebelum mutasi. Jalur ini tidak
     * boleh mengubah unit penanggung jawab: `unit_id` wajib sama dengan
     * baris terkunci dan pemindahan unit hanya dilayani endpoint pindah-unit
     * khusus. Kolom `jenis_agregasi` beku MVP dan tidak pernah ditulis dari
     * request.
     *
     * @return array{indikator: IndikatorKinerja, renstraId: ?string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $validated): array
    {
        $result = DB::transaction(function () use ($indikator, $validated, $actor) {
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

            // 2b. Guard rujukan regulasi: tanpa regulasi:read efektif,
            // pelepasan (regulasi_id null) DIABAIKAN — nilai lama dipertahankan
            // dan request tetap sukses — agar null tak meloloskan pelepasan
            // tanpa izin baca. Penautan non-null tanpa izin baca tetap 403.
            // Dengan izin baca, target dikunci + dicek ulang di bawah.
            $inputHasRegulasi = array_key_exists('regulasi_id', $validated);
            $rawRegulasiId = $validated['regulasi_id'] ?? null;
            if ($rawRegulasiId === '') {
                $rawRegulasiId = null;
            }
            $wantsClear = $inputHasRegulasi && $rawRegulasiId === null;
            $wantsLink = $inputHasRegulasi && $rawRegulasiId !== null;
            $abaikanRegulasi = false;
            if ($wantsClear || $wantsLink) {
                $regulasiDecision = $this->resolver->resolve($lockedActor, PermissionCodes::REGULASI_READ);
                if (! $regulasiDecision->allowed) {
                    if ($wantsClear) {
                        $abaikanRegulasi = true;
                    } else {
                        return [
                            'status' => 'denied',
                            'alasan' => 'Penautan regulasi ditolak karena Anda tidak berwenang membaca data regulasi yang dirujuk.',
                            'dasarIzin' => $regulasiDecision->toAuditBasis(),
                        ];
                    }
                }
            }

            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $expectedRaw = $validated['expected_updated_at'] ?? null;
            if ($expectedRaw !== null && trim((string) $expectedRaw) !== '') {
                try {
                    $expectedIso = Carbon::parse((string) $expectedRaw)->toISOString();
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        'expected_updated_at' => 'Format timestamp versi tidak valid.',
                    ]);
                }

                $currentTimestamp = $lockedIndikator->updated_at ?? $lockedIndikator->created_at;
                $currentIso = $currentTimestamp !== null ? Carbon::parse($currentTimestamp)->toISOString() : null;

                if ($currentIso === null || $currentIso !== $expectedIso) {
                    throw ValidationException::withMessages([
                        'konflik' => 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                    ])->status(409);
                }
            }

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

            // 4. Edit umum tidak boleh memindahkan unit penanggung jawab.
            // `unit_id` wajib sama dengan baris terkunci (anti-TOCTOU);
            // pemindahan unit hanya lewat endpoint pindah-unit khusus.
            if ($validated['unit_id'] !== $lockedIndikator->unit_id) {
                throw ValidationException::withMessages([
                    'unit_id' => 'Unit penanggung jawab tidak dapat diubah melalui edit umum. Gunakan endpoint pindah unit khusus untuk memindahkan indikator ke unit lain.',
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
            // 4b. Kunci regulasi target dan periksa ulang status aktif di dalam
            // transaksi (anti-TOCTOU antara validasi request dan UPDATE),
            // mengikuti pola kunci unit/sasaran di atas. Tanpa izin baca +
            // null sudah ditandai abaikan di 2b — nilai lama dipertahankan.
            if ($inputHasRegulasi && ! $abaikanRegulasi) {
                if ($wantsClear) {
                    $updateData['regulasi_id'] = null;
                } else {
                    /** @var Regulasi|null $targetRegulasi */
                    $targetRegulasi = Regulasi::whereKey($rawRegulasiId)->sharedLock()->first();
                    if (! $targetRegulasi || ! $targetRegulasi->aktif) {
                        throw ValidationException::withMessages([
                            'regulasi_id' => 'Rujukan regulasi tidak valid atau sudah nonaktif.',
                        ]);
                    }
                    $updateData['regulasi_id'] = $rawRegulasiId;
                }
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

            $lockedIndikator->update($updateData);

            $nilaiBaru = $lockedIndikator->withoutRelations()->toArray();

            // 5. Audit perubahan data umum jika ada field yang berubah
            $generalFields = ['sasaran_strategis_id', 'regulasi_id', 'kode', 'nama', 'definisi_operasional', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'wajib_catatan'];
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
                'renstraId' => $targetSasaran->renstra_id,
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

        return ['indikator' => $result['indikator'], 'renstraId' => $result['renstraId']];
    }
}
