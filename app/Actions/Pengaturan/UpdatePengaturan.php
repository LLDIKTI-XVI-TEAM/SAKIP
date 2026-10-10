<?php

namespace App\Actions\Pengaturan;

use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\PengaturanService;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdatePengaturan
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Simpan pembaruan pengaturan dengan validasi whitelist, otorisasi, alasan audit wajib, dan deteksi konflik.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $expectedUpdatedAt
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data, string $alasan, array $expectedUpdatedAt = []): int
    {
        $auditReason = trim($alasan);
        if (mb_strlen($auditReason) < 5) {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembaruan pengaturan minimal 5 karakter untuk catatan audit.',
            ]);
        }

        $disallowed = array_diff(array_keys($data), array_keys(PengaturanService::WHITELIST));
        if ($disallowed !== []) {
            $invalidKey = array_values($disallowed)[0];
            throw ValidationException::withMessages([
                $invalidKey => "Kunci pengaturan '{$invalidKey}' tidak diizinkan untuk diubah.",
            ]);
        }

        $changedCount = 0;
        $changedKeys = [];

        $result = DB::transaction(function () use ($actor, $data, $auditReason, $expectedUpdatedAt, &$changedCount, &$changedKeys) {
            $lockedActor = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'denialReason' => 'Akun pengguna tidak aktif atau tidak ditemukan.',
                    'denialBasis' => [
                        'permission' => PermissionCodes::PENGATURAN_UPDATE,
                        'keputusan' => 'ditolak',
                        'alasan' => 'Akun pengguna tidak aktif atau tidak ditemukan.',
                    ],
                ];
            }

            // Kunci relasi peran pengguna
            $roleIds = DB::table('user_roles')
                ->where('user_id', $lockedActor->id)
                ->lockForUpdate()
                ->pluck('role_id')
                ->all();

            // Semua user lock mendahului role; role selalu UUID-sorted (selaras dengan ChangeRolePermission)
            if ($roleIds !== []) {
                Role::query()->whereIn('id', array_unique($roleIds))->orderBy('id')->lockForUpdate()->get();
            }

            // Shared lock permission pengaturan:update untuk menyelaraskan dengan mutasi hak akses
            Permission::query()->where('kode', PermissionCodes::PENGATURAN_UPDATE)->orderBy('id')->sharedLock()->first();

            $decision = $this->permissionResolver->resolve($lockedActor, PermissionCodes::PENGATURAN_UPDATE);
            if (! $decision->allowed) {
                return [
                    'status' => 'denied',
                    'denialReason' => 'Anda tidak memiliki izin untuk mengubah pengaturan sistem.',
                    'denialBasis' => $decision->toAuditBasis(),
                ];
            }

            $now = Carbon::now();

            // Urutkan kunci secara deterministik untuk mencegah potensi deadlock konkurensi antar transaksi
            ksort($data);

            foreach ($data as $kunci => $nilaiBaru) {
                // Serialisasikan pembuatan dan pembaruan baris per kunci pada PostgreSQL untuk mencegah konflik baris baru
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['sakip:pengaturan:'.$kunci]);
                }

                $nilaiBaruStr = $nilaiBaru !== null ? (string) $nilaiBaru : null;

                $setting = Pengaturan::query()->lockForUpdate()->firstOrNew(['kunci' => $kunci]);

                if ($setting->exists) {
                    if (! array_key_exists($kunci, $expectedUpdatedAt) || ! is_string($expectedUpdatedAt[$kunci]) || trim($expectedUpdatedAt[$kunci]) === '') {
                        throw ValidationException::withMessages([
                            $kunci => "Token versi untuk pengaturan '{$kunci}' wajib disertakan.",
                        ]);
                    }

                    try {
                        $expected = Carbon::parse($expectedUpdatedAt[$kunci]);
                    } catch (\Throwable) {
                        throw ValidationException::withMessages([
                            $kunci => "Format token versi untuk pengaturan '{$kunci}' tidak valid.",
                        ]);
                    }

                    if ($setting->updated_at !== null && $setting->updated_at->toISOString() !== $expected->toISOString()) {
                        $updaterName = $setting->updatedBy?->nama ?? 'pengguna lain';
                        throw ValidationException::withMessages([
                            $kunci => "Pengaturan '{$kunci}' telah diperbarui oleh {$updaterName} saat Anda sedang mengedit. Silakan muat ulang halaman.",
                        ]);
                    }
                } else {
                    if (array_key_exists($kunci, $expectedUpdatedAt) && is_string($expectedUpdatedAt[$kunci]) && trim($expectedUpdatedAt[$kunci]) !== '') {
                        throw ValidationException::withMessages([
                            $kunci => "Pengaturan '{$kunci}' belum tersimpan di basis data sehingga tidak memiliki token versi sebelumnya.",
                        ]);
                    }
                }

                $nilaiLama = $setting->nilai;

                if ($setting->exists && $nilaiLama === $nilaiBaruStr) {
                    continue;
                }

                $setting->nilai = $nilaiBaruStr;
                $setting->tipe = PengaturanService::WHITELIST[$kunci]['tipe'];
                $setting->grup = PengaturanService::WHITELIST[$kunci]['grup'];
                $setting->updated_by = $lockedActor->id;
                $setting->updated_at = $now;

                try {
                    $setting->save();
                } catch (UniqueConstraintViolationException) {
                    $updaterName = Pengaturan::query()->where('kunci', $kunci)->first()?->updatedBy?->nama ?? 'pengguna lain';
                    throw ValidationException::withMessages([
                        $kunci => "Pengaturan '{$kunci}' telah dibuat oleh {$updaterName} saat Anda sedang mengedit. Silakan muat ulang halaman.",
                    ]);
                }

                $changedKeys[] = $kunci;

                $this->auditLogger->catat(
                    actor: $lockedActor,
                    tindakan: 'pengaturan:update',
                    objekTipe: 'pengaturan',
                    objekId: (string) $setting->id,
                    nilaiLama: ['nilai' => $nilaiLama],
                    nilaiBaru: ['nilai' => $nilaiBaruStr],
                    alasan: $auditReason,
                    dasarIzin: $decision->toAuditBasis(),
                );

                $changedCount++;
            }

            DB::afterCommit(function () use ($changedKeys) {
                try {
                    $newRevision = (string) Carbon::now()->format('YmdHisu').'_'.bin2hex(random_bytes(4));
                    Cache::forever('pengaturan.revision', $newRevision);

                    foreach ($changedKeys as $kunci) {
                        Cache::forget("pengaturan.{$kunci}");
                    }
                    Cache::forget('pengaturan.all');
                    Cache::forget('pengaturan.all_values');
                } catch (\Throwable $e) {
                    Log::warning('Gagal menginvalidasi cache pengaturan setelah commit basis data: '.$e->getMessage(), [
                        'changed_keys' => $changedKeys,
                        'exception' => $e,
                    ]);

                    // Jadwalkan retry invalidasi setelah respons selesai dikirim ke pengguna
                    try {
                        dispatch(function () use ($changedKeys) {
                            $newRevision = (string) Carbon::now()->format('YmdHisu').'_'.bin2hex(random_bytes(4));
                            Cache::forever('pengaturan.revision', $newRevision);

                            foreach ($changedKeys as $kunci) {
                                Cache::forget("pengaturan.{$kunci}");
                            }
                            Cache::forget('pengaturan.all');
                            Cache::forget('pengaturan.all_values');
                        })->afterResponse();
                    } catch (\Throwable) {
                        // Abaikan jika dispatcher tidak tersedia dalam konteks pengujian
                    }
                }
            });

            return [
                'status' => 'success',
                'count' => $changedCount,
            ];
        });

        if (is_array($result) && ($result['status'] ?? null) === 'denied') {
            $this->catatAuditPenolakan($actor, $data, $auditReason, $result['denialBasis']);
            throw new AuthorizationException($result['denialReason']);
        }

        return $changedCount;
    }

    /**
     * Catat percobaan pembaruan pengaturan yang ditolak ke dalam audit trail di luar transaksi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $dasarIzin
     */
    private function catatAuditPenolakan(User $actor, array $data, string $alasan, array $dasarIzin): void
    {
        try {
            $targetKey = null;
            foreach (array_keys($data) as $key) {
                if ($key !== 'alasan' && ! str_starts_with($key, 'expected_updated_at')) {
                    $targetKey = $key;
                    break;
                }
            }

            $objekId = $targetKey ? Pengaturan::query()->where('kunci', $targetKey)->value('id') : null;
            if (! $objekId) {
                $objekId = (string) Str::uuid();
            }

            $alasanAudit = trim($alasan) !== ''
                ? mb_substr(trim($alasan), 0, 255)
                : 'Percobaan pembaruan pengaturan sistem ditolak karena tidak memiliki izin.';

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'pengaturan.ubah_ditolak',
                objekTipe: 'pengaturan',
                objekId: (string) $objekId,
                alasan: $alasanAudit,
                dasarIzin: $dasarIzin,
            );
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit penolakan otorisasi pengaturan: '.$e->getMessage(), [
                'actor_id' => $actor->id,
                'exception' => $e,
            ]);
        }
    }
}
