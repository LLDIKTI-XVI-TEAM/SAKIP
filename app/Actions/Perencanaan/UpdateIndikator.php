<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
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

            // 2b. Guard rujukan regulasi (Q3): bila regulasi_id diisi non-null,
            // aktor wajib lolos regulasi:read memakai state terkunci agar tebakan
            // UUID tak bisa menautkan dasar hukum tanpa izin baca. Gagal → 403 + audit.
            if (array_key_exists('regulasi_id', $validated) && $validated['regulasi_id'] !== null && $validated['regulasi_id'] !== '') {
                $regulasiDecision = $this->resolver->resolve($lockedActor, PermissionCodes::REGULASI_READ);
                if (! $regulasiDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'alasan' => 'Penautan regulasi ditolak karena Anda tidak berwenang membaca data regulasi yang dirujuk.',
                        'dasarIzin' => $regulasiDecision->toAuditBasis(),
                    ];
                }
            }

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
