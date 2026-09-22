<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\User;
use App\Support\PermissionCodes;
use Database\Seeders\PengaturanSeeder as SeederPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        return Cache::remember("pengaturan.{$kunci}", 86400, function () use ($kunci, $default) {
            $setting = Pengaturan::query()->where('kunci', $kunci)->first();

            if ($setting === null) {
                return $default ?? $this->getDefault($kunci);
            }

            return $this->castValue($setting->nilai, $setting->tipe);
        });
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
            $nilai = $record?->nilai ?? $this->getDefault($kunci);
            $values[$kunci] = $nilai;

            $item = [
                'kunci' => $kunci,
                'nilai' => $nilai,
                'tipe' => $meta['tipe'],
                'grup' => $meta['grup'],
                'label' => $meta['label'],
                'updated_at' => $record?->updated_at?->toIso8601String(),
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
     * Simpan pembaruan pengaturan dengan validasi whitelist, otorisasi, dan pencatatan audit.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function update(User $actor, array $data, ?string $alasan = null): int
    {
        // 1. Otorisasi aktor
        $decision = $this->permissionResolver->resolve($actor, PermissionCodes::PENGATURAN_UPDATE);
        if (! $decision->allowed) {
            throw new AuthorizationException('Anda tidak memiliki izin untuk mengubah pengaturan sistem.');
        }

        // 2. Strict Whitelist guard
        $disallowed = array_diff(array_keys($data), array_keys(self::WHITELIST));
        if ($disallowed !== []) {
            $invalidKey = array_values($disallowed)[0];
            throw ValidationException::withMessages([
                $invalidKey => "Kunci pengaturan '{$invalidKey}' tidak diizinkan untuk diubah.",
            ]);
        }

        $auditReason = $alasan ?: 'Pembaruan identitas dan preferensi sistem.';
        $changedCount = 0;

        DB::transaction(function () use ($actor, $data, $decision, $auditReason, &$changedCount) {
            $now = Carbon::now();

            foreach ($data as $kunci => $nilaiBaru) {
                // Normalisasi string kosong menjadi null untuk field opsional jika relevan
                $nilaiBaruStr = $nilaiBaru !== null ? (string) $nilaiBaru : null;

                $setting = Pengaturan::query()->lockForUpdate()->firstOrNew(['kunci' => $kunci]);
                $nilaiLama = $setting->nilai;

                if ($setting->exists && $nilaiLama === $nilaiBaruStr) {
                    continue;
                }

                $setting->nilai = $nilaiBaruStr;
                $setting->tipe = self::WHITELIST[$kunci]['tipe'];
                $setting->grup = self::WHITELIST[$kunci]['grup'];
                $setting->updated_by = $actor->id;
                $setting->updated_at = $now;
                $setting->save();

                Cache::forget("pengaturan.{$kunci}");

                $this->auditLogger->catat(
                    actor: $actor,
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

            Cache::forget('pengaturan.all');
        });

        return $changedCount;
    }

    /**
     * Kosongkan seluruh cache pengaturan.
     */
    public function flushCache(): void
    {
        foreach (array_keys(self::WHITELIST) as $kunci) {
            Cache::forget("pengaturan.{$kunci}");
        }
        Cache::forget('pengaturan.all');
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
