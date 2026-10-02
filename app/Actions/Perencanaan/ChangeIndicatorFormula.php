<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kinerja\IndikatorPerhitunganService;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChangeIndicatorFormula
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly IndikatorPerhitunganService $perhitunganService,
    ) {}

    /**
     * Mengubah tipe perhitungan sekaligus melengkapi komponen dalam satu transaksi atomik.
     *
     * Satu-satunya jalur transisi manual↔nonmanual yang sah (Opsi A R3-01):
     * kandidat = tipe baru + konfigurasi komponen final (existing + payload)
     * dinilai penuh via `validateDefinisiKomponen` sebelum mutasi apa pun.
     * Gagal → 422 tanpa mutasi/audit sukses. Sukses → update tipe + create
     * komponen + audit (`indikator.ubah` + `komponen.buat` per baris) dalam
     * SATU commit.
     *
     * Urutan kunci global tetap: Regulasi(S) → Indikator(X) → Sasaran(S).
     * Jalur ini tidak membaca/menulis regulasi_id sehingga tidak mengunci
     * baris Regulasi sama sekali (kasus null = lewati, permanen) — tidak ada
     * jalur Indikator→Regulasi di sini, seperti PindahUnitIndikator. Parent
     * dikunci FOR UPDATE sebelum insert child (konsisten parent→child dengan
     * IndikatorKomponenController, tanpa inversi = tanpa deadlock).
     *
     * Izin: `indikator:update` (re-auth via ResolveLockedActor) +
     * `komponen:create` (state terkunci, fail-closed). Stale-token wajib +
     * fail-closed seperti UpdateIndikator.
     *
     * @param  array{tipe_perhitungan: string, komponen?: list<array<string, mixed>>, expected_updated_at: string}  $validated
     * @return array{indikator: IndikatorKinerja, renstraId: ?string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $validated): array
    {
        $result = DB::transaction(function () use ($indikator, $validated, $actor) {
            // 1. Re-auth indikator:update memakai state terkunci.
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_UPDATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Perubahan formula indikator ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Perubahan formula indikator ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            // 1b. Jalur atomik membuat baris komponen baru sehingga wajib
            // lolos komponen:create memakai state terkunci (fail-closed agar
            // tak menjadi bypass guard komponen).
            $komponenDecision = $this->resolver->resolve($lockedActor, 'komponen:create');
            if (! $komponenDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Perubahan formula indikator ditolak karena Anda tidak berwenang menambah komponen perhitungan.',
                    'dasarIzin' => $komponenDecision->toAuditBasis(),
                ];
            }

            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Stale-token wajib + fail-closed (pola UpdateIndikator).
            $expectedRaw = $validated['expected_updated_at'] ?? null;
            if ($expectedRaw === null || trim((string) $expectedRaw) === '') {
                throw ValidationException::withMessages([
                    'konflik' => 'Data indikator kinerja telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                ])->status(409);
            }

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

            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // 3. Kandidat = tipe baru + final komponen (existing + payload
            // sebagai model in-memory) dinilai penuh via satu-satunya penentu
            // validitas sebelum mutasi apa pun.
            $kandidatTipe = $validated['tipe_perhitungan'];
            $payloadKomponen = $validated['komponen'] ?? [];
            $existing = $lockedIndikator->komponen()->get();

            $payloadModels = collect($payloadKomponen)->map(fn (array $item) => new IndikatorKomponen([
                'indikator_id' => $lockedIndikator->id,
                'kode' => trim((string) ($item['kode'] ?? '')),
                'label' => trim((string) ($item['label'] ?? '')),
                'peran' => $item['peran'] ?? null,
                'bobot' => $item['bobot'] ?? 0,
                'urutan' => (int) ($item['urutan'] ?? 1),
                'satuan' => isset($item['satuan']) && $item['satuan'] !== null ? trim((string) $item['satuan']) : null,
                'aktif' => array_key_exists('aktif', $item) ? (bool) $item['aktif'] : true,
            ]));

            $kandidat = clone $lockedIndikator;
            $kandidat->tipe_perhitungan = $kandidatTipe;
            $kandidat->setRelation('komponen', $existing->concat($payloadModels)->values());

            $validasi = $this->perhitunganService->validateDefinisiKomponen($kandidat);
            if (! $validasi['is_valid']) {
                throw ValidationException::withMessages([
                    'tipe_perhitungan' => $validasi['messages'],
                ]);
            }

            // 4. Sukses: update tipe (bila berubah) + create komponen payload
            // dalam SATU commit yang sama.
            $tipeBerubah = $lockedIndikator->tipe_perhitungan !== $kandidatTipe;
            if ($tipeBerubah) {
                $lockedIndikator->update(['tipe_perhitungan' => $kandidatTipe]);
            }

            $komponenBaru = [];
            try {
                foreach ($payloadKomponen as $item) {
                    $komponenBaru[] = IndikatorKomponen::create([
                        'indikator_id' => $lockedIndikator->id,
                        'kode' => trim((string) $item['kode']),
                        'label' => trim((string) $item['label']),
                        'peran' => $item['peran'],
                        'bobot' => $item['bobot'],
                        'urutan' => (int) $item['urutan'],
                        'satuan' => isset($item['satuan']) && $item['satuan'] !== null && trim((string) $item['satuan']) !== ''
                            ? trim((string) $item['satuan'])
                            : null,
                        'aktif' => array_key_exists('aktif', $item) ? (bool) $item['aktif'] : true,
                        'created_by' => $lockedActor->id,
                    ])->fresh();
                }
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw ValidationException::withMessages([
                        'kode' => 'Kode komponen sudah digunakan pada indikator ini.',
                    ]);
                }

                throw $e;
            }

            // 5. Sasaran dikunci bersama setelah Indikator (urutan global
            // Indikator(X) → Sasaran(S)) hanya untuk renstraId redirect.
            $renstraId = SasaranStrategis::whereKey($lockedIndikator->sasaran_strategis_id)
                ->sharedLock()
                ->value('renstra_id');
            $renstraId = is_string($renstraId) ? $renstraId : null;

            $nilaiBaru = $lockedIndikator->fresh()->withoutRelations()->toArray();

            // 6. Audit dalam transaksi yang sama: indikator.ubah bila tipe
            // berubah + komponen.buat per baris baru (pola existing).
            if ($tipeBerubah) {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.ubah',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: "Mengubah formula perhitungan indikator '{$lockedIndikator->kode}' dari {$nilaiLama['tipe_perhitungan']} ke {$kandidatTipe} beserta ".count($komponenBaru).' komponen dalam satu transaksi atomik.',
                    dasarIzin: $dasarIzin,
                );
            }

            foreach ($komponenBaru as $komponen) {
                /** @var IndikatorKomponen $komponen */
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.buat',
                    objekTipe: 'indikator_komponen',
                    objekId: $komponen->id,
                    nilaiLama: null,
                    nilaiBaru: $this->formatAuditSnapshot($komponen),
                    alasan: 'Penambahan komponen indikator '.$komponen->kode.' ('.$komponen->label.') via transisi formula atomik',
                    dasarIzin: $komponenDecision->toAuditBasis(),
                );
            }

            return [
                'status' => 'updated',
                'indikator' => $lockedIndikator->fresh(),
                'renstraId' => $renstraId,
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
                alasan: $result['alasan'] ?? 'Perubahan formula indikator ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang mengubah formula perhitungan indikator.');
        }

        return ['indikator' => $result['indikator'], 'renstraId' => $result['renstraId']];
    }

    /**
     * Membentuk snapshot audit dengan bobot eksak sebagai string (pola IndikatorKomponenController).
     *
     * @return array<string, mixed>
     */
    private function formatAuditSnapshot(IndikatorKomponen $komponen): array
    {
        $snapshot = $komponen->toArray();
        $rawBobot = $komponen->getRawOriginal('bobot');
        if ($rawBobot !== null && $rawBobot !== '') {
            $snapshot['bobot'] = (string) $rawBobot;
        } elseif (isset($snapshot['bobot'])) {
            $snapshot['bobot'] = (string) $snapshot['bobot'];
        }

        return $snapshot;
    }

    /**
     * Mengecek pelanggaran unique constraint pada kode komponen.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $errorCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        return $sqlState === '23505'
            || $errorCode === 1062
            || $errorCode === 19
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
