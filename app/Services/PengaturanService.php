<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\User;
use Database\Seeders\PengaturanSeeder as SeederPengaturan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Pembaca pengaturan sistem: whitelist kunci, nilai bertipe dengan default seeder, dan cache ber-revisi.
 * Dipisahkan karena dipakai lintas lapisan tanpa mutasi: IndexPengaturan, HandleInertiaRequests::allValues,
 * UpdatePengaturanRequest dan Action UpdatePengaturan (keduanya memakai whitelist).
 * Tidak menulis basis data, tidak mengambil lock, dan tidak mengaudit; seluruh mutasi, otorisasi live,
 * dan invalidasi cache sesudah commit dimiliki Action App\Actions\Pengaturan\UpdatePengaturan.
 * Kegagalan cache store jatuh ke pembacaan basis data agar pembaca tidak pernah gagal karena cache.
 */
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

            // Inisialisasi atomik create-if-absent: hanya simpan jika key belum ada di cache store
            Cache::add('pengaturan.revision', $initialRevision, 86400 * 365);

            // Selalu baca ulang token efektif yang benar-benar tersimpan di cache store untuk
            // mencegah initializer yang tertunda mengembalikan kandidat lokal yang kalah bersaing
            $effectiveRevision = Cache::get('pengaturan.revision');
            if (is_string($effectiveRevision) && $effectiveRevision !== '') {
                return $effectiveRevision;
            }

            return $initialRevision;
        } catch (\Throwable) {
            return '1';
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
