<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCodes;
use Database\Seeders\PengaturanSeeder as SeederPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengaturanService
{
    /**
     * Whitelist kunci pengaturan yang diizinkan untuk diubah secara dinamis.
     *
     * @var array<string, array{tipe: string, grup: string, label: string, aturan: array<int, string>}>
     */
    public const WHITELIST = [
        'instansi.nama' => [
            'tipe' => 'string',
            'grup' => 'instansi',
            'label' => 'Nama Instansi',
            'aturan' => ['required', 'string', 'max:255'],
        ],
        'instansi.alamat' => [
            'tipe' => 'text',
            'grup' => 'instansi',
            'label' => 'Alamat Instansi',
            'aturan' => ['nullable', 'string', 'max:1000'],
        ],
        'instansi.telepon' => [
            'tipe' => 'string',
            'grup' => 'instansi',
            'label' => 'Nomor Telepon',
            'aturan' => ['nullable', 'string', 'max:50'],
        ],
        'instansi.surel' => [
            'tipe' => 'string',
            'grup' => 'instansi',
            'label' => 'Surel / Email',
            'aturan' => ['nullable', 'string', 'email', 'max:100'],
        ],
        'instansi.laman' => [
            'tipe' => 'url',
            'grup' => 'instansi',
            'label' => 'Laman Resmi',
            'aturan' => ['nullable', 'string', 'url', 'max:255'],
        ],
        'instansi.logo' => [
            'tipe' => 'string',
            'grup' => 'instansi',
            'label' => 'Logo Instansi',
            'aturan' => ['nullable', 'string', 'max:255'],
        ],
        'aplikasi.nama' => [
            'tipe' => 'string',
            'grup' => 'aplikasi',
            'label' => 'Nama Aplikasi',
            'aturan' => ['required', 'string', 'max:255'],
        ],
        'aplikasi.label_unit' => [
            'tipe' => 'string',
            'grup' => 'aplikasi',
            'label' => 'Label Unit Kerja',
            'aturan' => ['required', 'string', 'max:100'],
        ],
        'tampilan.zona_waktu' => [
            'tipe' => 'string',
            'grup' => 'tampilan',
            'label' => 'Zona Waktu',
            'aturan' => ['required', 'string', 'in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura,UTC'],
        ],
        'tampilan.format_tanggal' => [
            'tipe' => 'string',
            'grup' => 'tampilan',
            'label' => 'Format Tanggal',
            'aturan' => ['required', 'string', 'in:d F Y,d/m/Y,Y-m-d'],
        ],
        'tampilan.format_angka' => [
            'tipe' => 'string',
            'grup' => 'tampilan',
            'label' => 'Format Angka',
            'aturan' => ['required', 'string', 'in:id_ID,en_US'],
        ],
        'laporan.header' => [
            'tipe' => 'text',
            'grup' => 'laporan',
            'label' => 'Header Laporan',
            'aturan' => ['nullable', 'string', 'max:1000'],
        ],
        'laporan.footer' => [
            'tipe' => 'text',
            'grup' => 'laporan',
            'label' => 'Footer Laporan',
            'aturan' => ['nullable', 'string', 'max:1000'],
        ],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Ambil nilai pengaturan berdasarkan kunci dengan caching dan type casting.
     */
    public function get(string $kunci, mixed $default = null): mixed
    {
        $revision = $this->getCacheRevision();
        $loader = function () use ($kunci, $default, $revision) {
            $setting = Pengaturan::query()->where('kunci', $kunci)->first();

            // Jika revisi telah berubah selama pembacaan database, muat ulang nilai terkini
            if ($this->getCacheRevision() !== $revision) {
                $setting = Pengaturan::query()->where('kunci', $kunci)->first();
            }

            if ($setting === null) {
                return $default ?? $this->getDefault($kunci);
            }

            return $this->castValue($setting->nilai, $setting->tipe);
        };

        try {
            return Cache::remember("pengaturan.{$revision}.{$kunci}", 3600, $loader);
        } catch (\Throwable) {
            return $loader();
        }
    }

    /**
     * Ambil seluruh pengaturan yang dikelompokkan berdasarkan grup.
     *
     * @return array{
     *     grouped: array<string, list<array<string, mixed>>>,
     *     values: array<string, mixed>
     * }
     */
    public function allGrouped(): array
    {
        $records = Pengaturan::query()
            ->with('updatedBy:id,nama')
            ->whereIn('kunci', array_keys(self::WHITELIST))
            ->get()
            ->keyBy('kunci');

        $grouped = [
            'instansi' => [],
            'aplikasi' => [],
            'tampilan' => [],
            'laporan' => [],
        ];

        $values = [];

        foreach (self::WHITELIST as $kunci => $meta) {
            $record = $records->get($kunci);
            // Pertahankan nilai null yang disengaja jika record sudah tersimpan di database
            $nilai = $record !== null ? $this->castValue($record->nilai, $meta['tipe']) : $this->getDefault($kunci);
            $values[$kunci] = $nilai;

            $item = [
                'kunci' => $kunci,
                'nilai' => $nilai,
                'tipe' => $meta['tipe'],
                'grup' => $meta['grup'],
                'label' => $meta['label'],
                'updated_at' => $record?->updated_at?->toISOString(),
                'updated_by' => ($record?->updatedBy instanceof User) ? [
                    'id' => $record->updatedBy->id,
                    'nama' => $record->updatedBy->nama,
                ] : null,
            ];

            $grouped[$meta['grup']][] = $item;
        }

        return [
            'grouped' => $grouped,
            'values' => $values,
        ];
    }

    /**
     * Ambil seluruh nilai pengaturan terkini dalam format key-value dengan caching.
     *
     * @return array<string, mixed>
     */
    public function allValues(): array
    {
        $revision = $this->getCacheRevision();
        $loader = function () use ($revision) {
            $records = Pengaturan::query()
                ->whereIn('kunci', array_keys(self::WHITELIST))
                ->get()
                ->keyBy('kunci');

            // Jika revisi telah berubah selama pembacaan database, muat ulang snapshot terkini
            if ($this->getCacheRevision() !== $revision) {
                $records = Pengaturan::query()
                    ->whereIn('kunci', array_keys(self::WHITELIST))
                    ->get()
                    ->keyBy('kunci');
            }

            $values = [];
            foreach (self::WHITELIST as $kunci => $meta) {
                $record = $records->get($kunci);
                $values[$kunci] = $record !== null
                    ? $this->castValue($record->nilai, $meta['tipe'])
                    : $this->getDefault($kunci);
            }

            return $values;
        };

        try {
            return Cache::remember("pengaturan.{$revision}.all_values", 3600, $loader);
        } catch (\Throwable) {
            return $loader();
        }
    }

    /**
     * Simpan pembaruan pengaturan dengan validasi whitelist, otorisasi, alasan audit wajib, dan deteksi konflik.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $expectedUpdatedAt
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $actor, array $data, string $alasan, array $expectedUpdatedAt = []): int
    {
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::PENGATURAN_UPDATE);
        if (! $decision->allowed) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengubah pengaturan sistem.');
        }

        $auditReason = trim($alasan);
        if (mb_strlen($auditReason) < 5) {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan pembaruan pengaturan minimal 5 karakter untuk catatan audit.',
            ]);
        }

        $disallowed = array_diff(array_keys($data), array_keys(self::WHITELIST));
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
            if (! $lockedActor || ! $lockedActor->is_active) {
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
                $setting->tipe = self::WHITELIST[$kunci]['tipe'];
                $setting->grup = self::WHITELIST[$kunci]['grup'];
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
     * Kosongkan seluruh cache pengaturan.
     */
    public function flushCache(): void
    {
        try {
            $newRevision = (string) Carbon::now()->format('YmdHisu').'_'.bin2hex(random_bytes(4));
            Cache::forever('pengaturan.revision', $newRevision);

            foreach (array_keys(self::WHITELIST) as $kunci) {
                Cache::forget("pengaturan.{$kunci}");
            }
            Cache::forget('pengaturan.all');
            Cache::forget('pengaturan.all_values');
        } catch (\Throwable $e) {
            Log::warning('Gagal mengosongkan seluruh cache pengaturan: '.$e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    /**
     * Ambil token revisi cache pengaturan terkini.
     */
    public function getCacheRevision(): string
    {
        try {
            $revision = Cache::get('pengaturan.revision');
            if (is_string($revision) && $revision !== '') {
                return $revision;
            }

            $maxUpdatedAt = Pengaturan::query()->max('updated_at');
            $initialRevision = $maxUpdatedAt !== null
                ? (string) Carbon::parse($maxUpdatedAt)->format('YmdHisu')
                : (string) (int) (microtime(true) * 1000000);

            Cache::forever('pengaturan.revision', $initialRevision);

            return $initialRevision;
        } catch (\Throwable) {
            return '1';
        }
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

    /**
     * Ambil default value dari seeder untuk kunci tertentu.
     */
    private function getDefault(string $kunci): ?string
    {
        static $defaults = null;

        if ($defaults === null) {
            $defaults = [];
            foreach (SeederPengaturan::DEFAULTS as $row) {
                $defaults[$row['kunci']] = $row['nilai'];
            }
        }

        return $defaults[$kunci] ?? null;
    }

    /**
     * Konversi tipe data pengaturan.
     */
    private function castValue(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'number' => is_numeric($value) ? (float) $value : $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value,
        };
    }
}
