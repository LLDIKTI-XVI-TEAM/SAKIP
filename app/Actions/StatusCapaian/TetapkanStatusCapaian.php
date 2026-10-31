<?php

namespace App\Actions\StatusCapaian;

use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\StatusCapaian;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Penetapan dan revisi status capaian manual pada satu versi pengukuran yang telah disahkan
 * (PRD §21, Data Model §2.21, Workflow §16). Keduanya adalah use case yang sama: revisi hanya
 * menulis baris baru, sedangkan baris lama tetap menjadi riwayat (soft replace).
 */
class TetapkanStatusCapaian
{
    private const KONFLIK = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';

    private const ALASAN_TETAP = 'Menetapkan status capaian pengukuran pada versi yang disahkan.';

    private const ALASAN_REVISI = 'Merevisi status capaian pengukuran pada versi yang sama; baris sebelumnya tetap tersimpan sebagai riwayat.';

    public function __construct(
        private readonly ResolveLockedActor $lockedActor,
        private readonly PermissionResolver $resolver,
        private readonly PengukuranKinerjaPolicy $policy,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{pengukuran_versi_id: string, status: string, versi: int|string, alasan?: ?string}  $data
     */
    public function handle(User $actor, string $pengukuranId, array $data): StatusCapaian
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        /** @var PermissionDecision|null $keputusanAwal */
        $keputusanAwal = null;
        $versiId = null;
        try {
            return DB::transaction(function () use ($actor, $pengukuranId, $data, &$decision, &$keputusanAwal, &$versiId): StatusCapaian {
                // Kunci aktor + ACL lebih dulu agar pencabutan izin menunggu, lalu keputusan dibaca per unit target.
                $locked = $this->lockedActor->handle($actor, PermissionCodes::STATUS_CAPAIAN_UPDATE);
                // Keputusan awal dipakai audit penolakan bila penolakan terjadi sebelum keputusan per unit terbentuk (mis. aktor nonaktif).
                $keputusanAwal = $locked['keputusan'];
                $aktor = $locked['aktor'];
                if (! $aktor) {
                    throw new AuthorizationException('Izin menetapkan status capaian tidak tersedia atau telah dicabut.');
                }
                $p = PengukuranKinerja::lockForUpdate()->findOrFail($pengukuranId);
                // Unit target selalu dari server; payload klien tidak pernah menentukan scope izin.
                $decision = $this->resolver->resolve($aktor, PermissionCodes::STATUS_CAPAIAN_UPDATE, $p->targetUnitId());
                if (! $decision->allowed) {
                    throw new AuthorizationException('Izin menetapkan status capaian tidak tersedia atau telah dicabut.');
                }

                $status = (string) ($data['status'] ?? '');
                if (! in_array($status, [StatusCapaian::STATUS_TERCAPAI, StatusCapaian::STATUS_BELUM_TERCAPAI], true)) {
                    throw ValidationException::withMessages(['status' => 'Status capaian tidak dikenal.']);
                }
                // Token konkurensi memakai `pengukuran_kinerjas.versi` (Q-F), dibandingkan di bawah lock header.
                $versiKlien = $data['versi'] ?? null;
                if (! is_int($versiKlien) && ! (is_string($versiKlien) && ctype_digit($versiKlien))) {
                    throw ValidationException::withMessages(['versi' => 'Token versi pengukuran tidak sah.']);
                }
                if ((int) $versiKlien !== $p->versi) {
                    throw ValidationException::withMessages(['versi' => self::KONFLIK])->status(409);
                }

                $versiId = strtolower(trim((string) ($data['pengukuran_versi_id'] ?? '')));
                if (! Str::isUuid($versiId)) {
                    throw ValidationException::withMessages(['pengukuran_versi_id' => 'Versi pengukuran tidak sah.']);
                }
                $versi = KinerjaSnapshot::whereKey($versiId)->where('pengukuran_id', $p->id)->lockForUpdate()->first();
                if (! $versi) {
                    throw ValidationException::withMessages(['pengukuran_versi_id' => 'Versi pengukuran tidak termasuk pengukuran ini.']);
                }
                // Header sudah dikunci eksklusif di atas dan semua penulis versi pengukuran ini juga mengunci header
                // lebih dulu, sehingga versi sah terakhir cukup dibaca tanpa lock baris kedua.
                $p->loadMissing('ratifiedVersion');
                $errors = $this->policy->statusCapaianErrors($p, $versi);
                if ($errors !== []) {
                    throw ValidationException::withMessages(['status_capaian' => $errors]);
                }

                $sebelum = StatusCapaian::berlaku($versi->id)->first();
                $baru = StatusCapaian::create([
                    'pengukuran_id' => $p->id,
                    'pengukuran_versi_id' => $versi->id,
                    'status' => $status,
                    'sumber' => StatusCapaian::SUMBER_MANUAL,
                    'ditetapkan_oleh' => $aktor->id,
                    'created_at' => CarbonImmutable::now(),
                ]);

                // Alasan opsional (Q78 §78.1 butir 4), tetapi pemanggil non-HTTP juga harus mendapat sanitasi dan batas
                // panjang yang sama dengan FormRequest; alasan baku server dipakai bila kosong.
                $alasan = AlasanAudit::sanitasi($data['alasan'] ?? null, $sebelum ? self::ALASAN_REVISI : self::ALASAN_TETAP);
                $this->audit->catat(
                    actor: $aktor,
                    tindakan: $sebelum ? 'status_capaian.revisi' : 'status_capaian.tetapkan',
                    objekTipe: 'status_capaian',
                    objekId: $baru->id,
                    nilaiLama: $sebelum ? [...$sebelum->only(['status', 'pengukuran_versi_id', 'sumber']), 'nomor' => $versi->nomor] : null,
                    nilaiBaru: [...$baru->only(['status', 'pengukuran_versi_id', 'sumber']), 'nomor' => $versi->nomor],
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                // Token konkurensi halaman adalah `pengukuran_kinerjas.versi`, jadi mutasi sukses wajib menaikkannya
                // (pola `PengukuranMutationSteps::finish`). Tanpa ini dua pengguna dengan token sama-sama berhasil dan
                // yang kedua diam-diam menimpa keputusan pertama. Increment berada di transaksi yang sama sehingga ikut
                // batal bila audit gagal.
                $p->versi++;
                $p->save();

                return $baru;
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Di luar rollback: penolakan tetap tercatat walau mutasi domain dibatalkan.
            $klasifikasi = match (true) {
                $exception instanceof AuthorizationException => 'izin_ditolak',
                $exception instanceof ValidationException && $exception->status === 409 => 'konflik_versi',
                default => 'validasi_bisnis',
            };
            $this->audit->catat(
                actor: $actor,
                tindakan: 'status_capaian.tetapkan_ditolak',
                // Penolakan merujuk pengukuran yang dituju, bukan UUID acak; `pengukuran_id` di nilai_baru tetap
                // dikirim agar penelusuran lama dan filter UI tidak berubah.
                objekTipe: 'pengukuran',
                objekId: strtolower($pengukuranId),
                nilaiBaru: ['alasan_penolakan' => $klasifikasi, 'pengukuran_id' => $pengukuranId,
                    'pengukuran_versi_id' => Str::isUuid((string) $versiId) ? $versiId : null,
                    'status' => isset($data['status']) ? (string) $data['status'] : null,
                    'pesan' => $exception->getMessage()],
                alasan: 'Penolakan penetapan status capaian pengukuran.',
                dasarIzin: $decision?->toAuditBasis() ?? $keputusanAwal?->toAuditBasis(),
            );

            throw $exception;
        }
    }
}
