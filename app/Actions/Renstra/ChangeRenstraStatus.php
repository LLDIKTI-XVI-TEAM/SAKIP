<?php

namespace App\Actions\Renstra;

use App\Models\Renstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeRenstraStatus
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
    ) {}

    /** Transisi berasal dari intent server; state dan izin diperiksa setelah lock aktor lalu master. */
    public function execute(Renstra $renstra, User $actor, string $intent, string $expectedState): Renstra
    {
        [$from, $to, $reason] = match ($intent) {
            'activate' => [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF, 'Aktivasi Renstra dari draft menjadi aktif.'],
            'deactivate' => [Renstra::STATUS_AKTIF, Renstra::STATUS_NONAKTIF, 'Nonaktivasi Renstra dari aktif menjadi nonaktif.'],
            'archive' => [Renstra::STATUS_NONAKTIF, Renstra::STATUS_DIARSIPKAN, 'Pengarsipan Renstra nonaktif untuk riwayat.'],
            default => throw new \InvalidArgumentException('Intent lifecycle Renstra tidak dikenal.'),
        };
        $before = $renstra->masterAttributes();
        $decision = $this->resolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);

        $denialCode = 'izin_ditolak';

        try {
            return DB::transaction(function () use ($renstra, &$actor, &$decision, &$before, $intent, $expectedState, $from, $to, $reason, &$denialCode): Renstra {
                $denialCode = 'izin_ditolak';
                $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $current = Renstra::query()->lockForUpdate()->findOrFail($renstra->id);
                $before = $current->masterAttributes();
                $decision = $this->resolver->resolve($actor, PermissionCodes::RENSTRA_UPDATE);
                if (! $decision->allowed) {
                    throw new AuthorizationException('Izin efektif Anda tidak mengizinkan tindakan ini.');
                }
                $denialCode = 'state_berubah';
                if (! hash_equals($current->stateToken(), $expectedState)) {
                    throw ValidationException::withMessages(['expected_state' => 'Renstra telah berubah. Muat data terbaru sebelum mengirim perubahan kembali.']);
                }
                $denialCode = 'transisi_tidak_valid';
                if ($current->status !== $from) {
                    throw ValidationException::withMessages(['renstra' => 'Transisi status Renstra tidak valid.']);
                }
                if ($intent === 'activate') {
                    $denialCode = 'rentang_tahun_tidak_valid';
                    Validator::make(['tahun_mulai' => $current->tahun_mulai, 'tahun_selesai' => $current->tahun_selesai], [
                        'tahun_mulai' => ['integer', 'between:2000,2100'],
                        'tahun_selesai' => ['integer', 'between:2000,2100', 'gte:tahun_mulai'],
                    ])->validate();
                    $denialCode = 'dasar_hukum_kosong';
                    if (trim((string) $current->dasar_hukum) === '') {
                        throw ValidationException::withMessages(['dasar_hukum' => 'Dasar hukum wajib diisi sebelum aktivasi Renstra.']);
                    }
                    $denialCode = 'rentang_aktif_beririsan';
                    // Range inklusif: berbagi satu tahun saja sudah merupakan overlap.
                    if (Renstra::query()->whereKeyNot($current->id)->where('status', Renstra::STATUS_AKTIF)
                        ->where('tahun_mulai', '<=', $current->tahun_selesai)->where('tahun_selesai', '>=', $current->tahun_mulai)->exists()) {
                        throw ValidationException::withMessages(['tahun_mulai' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.']);
                    }
                }
                $denialCode = 'jadwal_aktif';
                if ($intent === 'deactivate' && $current->jadwalTahunan()->where('status', 'aktif')->exists()) {
                    throw ValidationException::withMessages(['renstra' => 'Tutup seluruh Jadwal aktif sebelum menonaktifkan Renstra.']);
                }
                $current->status = $to;
                $current->save();
                $this->audit->catat(
                    actor: $actor, tindakan: 'renstra.'.$intent, objekTipe: 'renstra', objekId: $current->id,
                    nilaiLama: $before, nilaiBaru: $current->masterAttributes(), alasan: $reason, dasarIzin: $decision->toAuditBasis(),
                );

                return $current;
            }, attempts: 3);
        } catch (ValidationException|AuthorizationException|QueryException $exception) {
            if ($exception instanceof QueryException) {
                if (($exception->errorInfo[0] ?? null) !== '23P01' || ! str_contains($exception->getMessage(), 'renstras_active_years_exclude')) {
                    throw $exception;
                }
                $denialCode = 'rentang_aktif_beririsan';
                $exception = ValidationException::withMessages(['tahun_mulai' => 'Rentang tahun Renstra aktif beririsan dengan Renstra aktif lain.']);
            }
            // Catat penolakan setelah rollback agar histori percobaan tetap append-only.
            $this->audit->catat(
                actor: $actor, tindakan: 'renstra.'.$intent.'_ditolak', objekTipe: 'renstra', objekId: $renstra->id,
                nilaiLama: $before, nilaiBaru: ['hasil' => 'ditolak', 'alasan_penolakan' => $denialCode], alasan: 'Permintaan perubahan status Renstra ditolak.', dasarIzin: $decision->toAuditBasis(),
            );

            throw $exception;
        }
    }
}
