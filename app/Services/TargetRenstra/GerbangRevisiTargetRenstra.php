<?php

namespace App\Services\TargetRenstra;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\RevisiTargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use App\Support\TargetTahunanDecimal;
use App\Support\TargetTahunanState;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batas bersama aksi revisi target Renstra (Q71): izin dinilai ulang di dalam transaksi, penolakan diaudit di luar rollback.
 * Urutan lock Renstra → draf; simpan revisi mengunci indikator lebih dulu, sejalan dengan TargetRenstraState::lock.
 */
class GerbangRevisiTargetRenstra
{
    public const KONFLIK = 'Draf revisi sudah berubah atau tidak lagi terbuka. Muat ulang untuk melanjutkan.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /**
     * @template T
     *
     * @param  Closure(array<string, mixed>): T  $mutasi
     * @return T
     */
    public function jalankan(User $actor, string $renstraId, string $aksi, Closure $mutasi): mixed
    {
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $mutasi, &$basis): mixed {
                foreach ([PermissionCodes::INDIKATOR_READ, PermissionCodes::TARGET_UPDATE] as $permission) {
                    $basis[$permission] = $this->lockedActor->handle($actor, $permission)['keputusan']->toAuditBasis();
                }
                if ($basis[PermissionCodes::INDIKATOR_READ]['keputusan'] !== 'diizinkan' || $basis[PermissionCodes::TARGET_UPDATE]['keputusan'] !== 'diizinkan') {
                    throw new AuthorizationException('Revisi target Renstra memerlukan kedua izin yang efektif.');
                }

                return $mutasi($basis);
            });
        } catch (AuthorizationException|ValidationException $exception) {
            $alasan = match (true) {
                $exception instanceof AuthorizationException => 'izin_ditolak',
                $exception->status === 409 => 'versi_usang',
                default => 'input_atau_state_tidak_valid',
            };
            // Tanpa payload mentah: hanya aksi dan kategori penolakan.
            $this->audit->catat(actor: $actor, tindakan: 'target_renstra.revisi_ditolak', objekTipe: 'renstra', objekId: $renstraId,
                nilaiBaru: ['aksi' => $aksi, 'alasan_penolakan' => $alasan], dasarIzin: $basis);
            throw $exception;
        }
    }

    public function renstraAktif(string $renstraId, bool $eksklusif = false): Renstra
    {
        $renstra = Renstra::whereKey($renstraId)->lock($eksklusif ? true : 'for share')->firstOrFail();
        if ($renstra->status !== Renstra::STATUS_AKTIF) {
            throw ValidationException::withMessages(['renstra' => 'Mode revisi hanya tersedia untuk Renstra aktif.']);
        }

        return $renstra;
    }

    /** Sel draf hanya untuk indikator aktif milik Renstra; diperiksa saat autosave dan diulang saat simpan. */
    public function periksaIndikator(?IndikatorKinerja $indikator, string $id): IndikatorKinerja
    {
        if ($indikator === null) {
            throw ValidationException::withMessages(["nilai.{$id}" => 'Indikator bukan bagian dari Renstra ini.']);
        }
        if ($indikator->status !== IndikatorKinerja::STATUS_AKTIF) {
            throw ValidationException::withMessages(["nilai.{$id}" => 'Indikator arsip hanya dapat dibaca.']);
        }

        return $indikator;
    }

    /**
     * Aturan sel sama dengan simpan langsung: tahun berlaku, desimal Q33, dan presisi hanya untuk nilai yang berbeda dari
     * yang berlaku, agar presisi yang baru diturunkan tidak memaksa nilai lama.
     *
     * @param  array<int, ?string>  $berlaku
     */
    public function periksaSel(IndikatorKinerja $indikator, Renstra $renstra, int|string $tahun, mixed $raw, array $berlaku): ?string
    {
        $field = "nilai.{$indikator->id}.{$tahun}";
        if (! is_int($tahun) || ! TargetTahunanState::tahunBerlaku($indikator, $renstra, $tahun)) {
            throw ValidationException::withMessages([$field => 'Tahun harus berada dalam rentang Renstra dan tidak sebelum indikator mulai berlaku.']);
        }
        $next = TargetTahunanDecimal::normalize($raw, $field);
        if ($next !== ($berlaku[$tahun] ?? null)) {
            TargetTahunanDecimal::assertPrecision($next, $indikator->presisi, $field);
        }

        return $next;
    }

    public function drafTerkunci(Renstra $renstra, int $versi): RevisiTargetRenstra
    {
        $draf = RevisiTargetRenstra::where('renstra_id', $renstra->id)->where('status', RevisiTargetRenstra::STATUS_DRAF)->lockForUpdate()->first();
        if ($draf === null || $draf->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => self::KONFLIK])->status(409);
        }

        return $draf;
    }
}
