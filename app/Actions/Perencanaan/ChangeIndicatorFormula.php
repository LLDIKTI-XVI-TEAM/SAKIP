<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kinerja\KomponenMutationService;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChangeIndicatorFormula
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly ResolveLockedActor $lockedActor,
        private readonly KomponenMutationService $mutasiKomponen,
    ) {}

    /**
     * Payload adalah konfigurasi akhir, bukan tambahan pada formula existing.
     * Identitas child dipertahankan; child yang tidak disertakan dinonaktifkan,
     * sehingga referensi historis tidak dihapus. Seluruh kandidat dinilai sebelum
     * persist. Izin granular sesuai delta, versi induk, mutation dan audit atomic.
     * Gate baca komponen diwajibkan untuk setiap final-set sebelum child
     * dimuat (setelah kunci parent + cek token + guard alasan).
     * Urutan kunci: aktor/ACL → indikator → child → sasaran untuk redirect.
     * Jalur ini tidak membaca atau mengubah regulasi. Rationale operator
     * (`alasan`) tervalidasi dicatat pada audit induk dan child.
     *
     * @param  array{tipe_perhitungan: string, komponen?: list<array<string, mixed>>, expected_updated_at: string, alasan: string}  $validated
     * @return array{indikator: IndikatorKinerja, renstraId: ?string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, array $validated): array
    {
        $result = DB::transaction(function () use ($indikator, $validated, $actor) {
            // Izin dan ACL diperiksa sebelum mengunci induk/child.
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

            // Kunci ketiga permission sebelum parent; kewajiban create/update
            // ditentukan dari delta aktual setelah kandidat disusun, sedangkan
            // baca diwajibkan untuk setiap final-set sebelum child dimuat.
            $createDecision = $this->lockedActor->handle($lockedActor, 'komponen:create')['keputusan'];
            $updateDecision = $this->lockedActor->handle($lockedActor, 'komponen:update')['keputusan'];
            $readDecision = $this->lockedActor->handle($lockedActor, 'komponen:read')['keputusan'];

            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::query()
                ->whereKey($indikator->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Token dibandingkan dengan induk terkunci, termasuk perubahan child.
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

            // Rationale operator wajib hadir sebelum mutasi/audit apa pun.
            // HTTP sudah ditolak 422 oleh FormRequest; guard ini menutup
            // pemanggil Action-langsung dengan respons yang sama.
            $alasan = isset($validated['alasan']) && is_string($validated['alasan']) ? trim($validated['alasan']) : '';
            if ($alasan === '') {
                throw ValidationException::withMessages([
                    'alasan' => 'Alasan perubahan formula wajib diisi.',
                ]);
            }
            if (mb_strlen($alasan, 'UTF-8') < 5) {
                throw ValidationException::withMessages([
                    'alasan' => 'Alasan perubahan formula minimal 5 karakter.',
                ]);
            }

            // Gate baca final-set: tanpa wewenang baca efektif, child
            // tersembunyi tidak boleh dimuat/dimutasi via tebakan ID.
            // Diposisikan setelah kunci parent + cek token + guard alasan
            // (perilaku allow R7-03 utuh) dan sebelum kunci/muat child.
            if (! $readDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Perubahan formula indikator ditolak karena wewenang baca komponen tidak lagi berlaku.',
                    'dasarIzin' => $readDecision->toAuditBasis(),
                ];
            }

            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            $kandidatTipe = $validated['tipe_perhitungan'];
            $payloadKomponen = $validated['komponen'] ?? [];
            $existing = $lockedIndikator->komponen()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $payloadModels = collect($payloadKomponen)->map(function (array $item, int $index) use ($lockedIndikator, $existing) {
                $id = $item['id'] ?? null;
                if ($id !== null) {
                    if (! $existing->has($id)) {
                        throw ValidationException::withMessages(["komponen.{$index}.id" => 'Komponen tidak termasuk indikator ini.']);
                    }
                    $candidate = clone $existing->get($id);
                    $candidate->fill($this->mutasiKomponen->normalisasiInput($item));

                    return $candidate;
                }

                return $this->mutasiKomponen->modelKandidat($lockedIndikator->id, $item);
            });
            $retainedIds = $payloadModels->filter(fn ($item) => $item->exists)->pluck('id');
            $omitted = $existing->reject(fn ($item) => $retainedIds->contains($item->id))->map(function ($item) {
                $copy = clone $item;
                $copy->aktif = false;

                return $copy;
            });
            $finalKomponen = $omitted->concat($payloadModels)->values();
            if ($finalKomponen->pluck('kode')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['kode' => $this->mutasiKomponen->pesanKodeDuplikat()]);
            }

            // Izin granular dinilai sebelum validitas formula agar pemanggil
            // tanpa wewenang mutation tidak menerima informasi validitas.
            $newComponents = $finalKomponen->filter(fn ($item) => ! $item->exists);
            $changedComponents = $finalKomponen->filter(fn ($item) => $item->exists && $item->isDirty());
            foreach ([
                [$newComponents->isNotEmpty(), $createDecision],
                [$changedComponents->isNotEmpty(), $updateDecision],
            ] as [$required, $decision]) {
                if ($required && ! $decision->allowed) {
                    return [
                        'status' => 'denied',
                        'alasan' => 'Perubahan formula indikator ditolak karena wewenang mutation komponen tidak lagi berlaku.',
                        'dasarIzin' => $decision->toAuditBasis(),
                    ];
                }
            }

            $kandidat = clone $lockedIndikator;
            $kandidat->tipe_perhitungan = $kandidatTipe;
            $this->mutasiKomponen->pastikanDefinisiValid($kandidat, $finalKomponen, 'tipe_perhitungan');

            $komponenBaru = [];
            $komponenDiubah = [];
            try {
                // Lepaskan kode lama hanya setelah final set lolos validasi/izin.
                // Kode sementara tidak pernah commit atau masuk audit; ini menjaga
                // constraint unik saat dua identitas existing saling menukar kode.
                foreach ($changedComponents as $item) {
                    if ($item->isDirty('kode')) {
                        DB::table('indikator_komponen')->where('id', $item->id)
                            ->where('indikator_id', $lockedIndikator->id)
                            ->update(['kode' => '__'.str_replace('-', '', (string) Str::uuid())]);
                    }
                }
                foreach ($changedComponents as $item) {
                    $old = $this->mutasiKomponen->formatAuditSnapshot($existing->get($item->id));
                    $item->save();
                    $komponenDiubah[] = [$old, $item->fresh()];
                }
                foreach ($newComponents as $item) {
                    $komponenBaru[] = $this->mutasiKomponen->buat($lockedIndikator->id, $item->getAttributes(), $lockedActor->id);
                }
            } catch (QueryException $e) {
                if ($this->mutasiKomponen->isUniqueConstraintViolation($e)) {
                    throw ValidationException::withMessages([
                        'kode' => $this->mutasiKomponen->pesanKodeDuplikat(),
                    ]);
                }

                throw $e;
            }

            $lockedIndikator->tipe_perhitungan = $kandidatTipe;
            $this->mutasiKomponen->bumpVersiFormula($lockedIndikator);

            // Sasaran hanya dibaca untuk redirect setelah mutation formula.
            $renstraId = SasaranStrategis::whereKey($lockedIndikator->sasaran_strategis_id)
                ->sharedLock()
                ->value('renstra_id');
            $renstraId = is_string($renstraId) ? $renstraId : null;

            $nilaiBaru = $lockedIndikator->fresh()->withoutRelations()->toArray();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.ubah',
                objekTipe: 'indikator',
                objekId: (string) $lockedIndikator->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $nilaiBaru,
                alasan: "Menyimpan formula indikator '{$lockedIndikator->kode}' dari {$nilaiLama['tipe_perhitungan']} ke {$kandidatTipe} dengan ".count($komponenBaru).' komponen baru dan '.count($komponenDiubah).' komponen diubah secara atomik. Alasan: '.$alasan,
                dasarIzin: $dasarIzin,
            );

            foreach ($komponenBaru as $komponen) {
                /** @var IndikatorKomponen $komponen */
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.buat',
                    objekTipe: 'indikator_komponen',
                    objekId: $komponen->id,
                    nilaiLama: null,
                    nilaiBaru: $this->mutasiKomponen->formatAuditSnapshot($komponen),
                    alasan: 'Penambahan komponen indikator '.$komponen->kode.' ('.$komponen->label.') via transisi formula atomik. Alasan: '.$alasan,
                    dasarIzin: $createDecision->toAuditBasis(),
                );
            }
            foreach ($komponenDiubah as [$old, $item]) {
                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'komponen.ubah',
                    objekTipe: 'indikator_komponen',
                    objekId: $item->id,
                    nilaiLama: $old,
                    nilaiBaru: $this->mutasiKomponen->formatAuditSnapshot($item),
                    alasan: 'Penyesuaian komponen '.$item->kode.' dalam konfigurasi formula akhir. Alasan: '.$alasan,
                    dasarIzin: $updateDecision->toAuditBasis(),
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
}
