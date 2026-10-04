<?php

namespace App\Services\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * Helper komponen bersama create indikator, simpan definisi dan FormRequest:
 * sintaks, normalisasi eksak, kandidat, persistence row serta snapshot audit.
 * Action memiliki workflow, otorisasi, locking, transaksi dan audit. Helper
 * persistence berjalan di transaksi terkunci pemanggil; tidak membuka transaksi.
 * Validitas komposisi tetap dimiliki IndikatorPerhitunganService.
 */
class KomponenMutationService
{
    public function __construct(private readonly IndikatorPerhitunganService $perhitunganService) {}

    /**
     * Menilai seluruh komposisi kandidat sebelum mutation apa pun.
     * Induk dan koleksi yang disediakan pemanggil berasal dari transaksi terkunci.
     *
     * @param  Collection<int, IndikatorKomponen>  $komponen
     */
    public function pastikanDefinisiValid(IndikatorKinerja $indikator, Collection $komponen, string $key = 'komponen'): void
    {
        $kandidat = clone $indikator;
        $kandidat->setRelation('komponen', $komponen);
        $validasi = $this->perhitunganService->validateDefinisiKomponen($kandidat);
        if (! $validasi['is_valid']) {
            throw ValidationException::withMessages([$key => [...$validasi['messages'], 'Perbaiki tipe dan komponen secara atomik melalui Kelola Komponen atau editor definisi.']]);
        }
    }

    /**
     * Mutation child juga mengubah versi formula induk, termasuk saat jam dibekukan.
     * Gunakan presisi dan aturan monotonik model existing tanpa kolom versi tambahan.
     */
    public function bumpVersiFormula(IndikatorKinerja $indikator): void
    {
        $next = now();
        if ($indikator->updated_at && $next->lte($indikator->updated_at)) {
            $next = $indikator->updated_at->copy()->addMicrosecond();
        }
        $indikator->updated_at = $next;
        $indikator->save();
    }

    /**
     * Aturan sintaks murni satu baris komponen tanpa akses basis data.
     *
     * Sumber bersama jalur kandidat (transisi formula) dan aturan store
     * normal; bentuk error key flat (`kode`, `bobot`, ...) dipertahankan.
     * `distinct` hanya untuk payload daftar agar duplikat DALAM payload
     * tetap ditolak tanpa menilai state DB lama.
     *
     * @return array<string, mixed>
     */
    public function aturanSintaksItem(bool $denganDistinct = false): array
    {
        $kode = [
            'required',
            'string',
            'max:50',
            'regex:/^[a-zA-Z0-9_]+$/',
        ];
        if ($denganDistinct) {
            $kode[] = 'distinct';
        }

        return [
            'kode' => $kode,
            'label' => ['required', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'penjumlah'])],
            'bobot' => ['bail', 'required', 'string', 'regex:/^\d{1,9}(?:\.\d{1,12})?$/', 'numeric', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_numeric($value) && BigDecimal::of((string) $value)->isGreaterThan('999999999')) {
                    $fail('Bobot komponen tidak boleh melebihi 999.999.999.');
                }
            }],
            'urutan' => ['required', 'integer', 'min:1', 'max:32767'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    /** Aturan penyimpanan row baru, setelah Action membebaskan kode yang ditukar. */
    private function aturanItem(string $indikatorId): array
    {
        $aturan = $this->aturanSintaksItem();
        $aturan['kode'][] = Rule::unique('indikator_komponen', 'kode')->where(fn ($query) => $query->where('indikator_id', $indikatorId));

        return $aturan;
    }

    /** Batas 50 adalah batas operasional intent per request, bukan maksimum bisnis. */
    public function aturanDefinisi(): array
    {
        $rules = [
            'komponen' => ['sometimes', 'array', 'max:50'],
            'komponen.*' => ['array:id,kode,label,satuan,peran,bobot,urutan,aktif'],
            'komponen.*.id' => ['nullable', 'uuid', 'distinct'],
            'hapus_komponen_ids' => ['sometimes', 'array', 'max:50'],
            'hapus_komponen_ids.*' => ['required', 'uuid', 'distinct'],
            'request_id' => ['sometimes', 'uuid'],
            'return_to' => ['sometimes', 'in:komponen,sasaran-indikator'],
            'alasan' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
        foreach ($this->aturanSintaksItem(true) as $field => $validation) {
            $rules["komponen.*.{$field}"] = $validation;
        }

        return $rules;
    }

    /**
     * Peta pesan tunggal untuk satu baris komponen (kunci flat).
     *
     * Teks di sini menjadi acuan kedua endpoint; varian bersarang memakai
     * teks yang sama dengan prefix kunci berbeda.
     *
     * @return array<string, string>
     */
    public function pesanItem(): array
    {
        return [
            'kode.required' => 'Kode komponen wajib diisi.',
            'kode.string' => 'Kode komponen harus berupa teks.',
            'kode.max' => 'Kode komponen maksimal 50 karakter.',
            'kode.regex' => 'Kode komponen hanya boleh berisi huruf, angka, dan garis bawah (_).',
            'kode.distinct' => 'Kode komponen tidak boleh duplikat dalam satu transisi.',
            'kode.unique' => 'Kode komponen sudah digunakan pada indikator ini.',
            'label.required' => 'Label komponen wajib diisi.',
            'label.string' => 'Label komponen harus berupa teks.',
            'label.max' => 'Label komponen maksimal 255 karakter.',
            'satuan.string' => 'Satuan komponen harus berupa teks.',
            'satuan.max' => 'Satuan komponen maksimal 50 karakter.',
            'peran.required' => 'Peran komponen wajib dipilih.',
            'peran.string' => 'Peran komponen harus berupa teks.',
            'peran.in' => 'Peran komponen harus salah satu dari: pembilang, penyebut, penjumlah.',
            'bobot.string' => 'Bobot komponen harus dikirim sebagai string desimal.',
            'bobot.regex' => 'Bobot komponen harus berupa desimal nonnegatif dengan maksimal 12 digit pecahan.',
            'bobot.required' => 'Bobot komponen wajib diisi.',
            'bobot.numeric' => 'Bobot komponen harus berupa angka numerik.',
            'bobot.decimal' => 'Bobot komponen maksimal memiliki 12 digit pecahan desimal.',
            'bobot.min' => 'Bobot komponen minimal bernilai 0.',
            'bobot.max' => 'Bobot komponen tidak boleh melebihi 999.999.999.',
            'urutan.required' => 'Urutan komponen wajib diisi.',
            'urutan.integer' => 'Urutan komponen harus berupa bilangan bulat.',
            'urutan.min' => 'Urutan komponen minimal 1.',
            'urutan.max' => 'Urutan komponen tidak boleh melebihi 32.767.',
            'aktif.boolean' => 'Status aktif komponen harus bernilai benar atau salah.',
        ];
    }

    /**
     * Peta pesan per item untuk payload transisi formula.
     *
     * Teks identik dengan pesanItem(); hanya kunci memakai prefix
     * `komponen.*.` agar kontrak error frontend tidak berubah.
     *
     * @return array<string, string>
     */
    public function pesanBersarang(): array
    {
        $dasar = $this->pesanItem();
        $hasil = [];
        foreach ($dasar as $key => $pesan) {
            $hasil["komponen.*.{$key}"] = $pesan;
        }

        return $hasil;
    }

    /**
     * Menambah error bobot penyebut bila peran penyebut berbobot tidak positif.
     *
     * Cermin tunggal aturan penyebut>0 untuk FormRequest (after hook) dan
     * validator internal; pesan selalu dari pesanBobotPenyebut().
     *
     * @param  array<string, mixed>  $item
     */
    public function tambahErrorPenyebutBilaNol(ValidatorContract $validator, array $item, string $key = 'bobot'): void
    {
        if (($item['peran'] ?? null) !== 'penyebut' || $validator->errors()->has($key)) {
            return;
        }

        $raw = $item['bobot'] ?? '0';
        if (! is_numeric($raw)) {
            return;
        }
        if (BigDecimal::of((string) $raw)->isLessThanOrEqualTo('0')) {
            $validator->errors()->add($key, $this->pesanBobotPenyebut());
        }
    }

    /**
     * Menjalankan validator sintaks lengkap untuk satu baris komponen.
     *
     * Dipakai jalur internal `buat()` agar tidak ada penyimpanan yang lolos
     * sintaks bila FormRequest dilewati; mencakup unique-vs-DB sebagai
     * penjaga saat persist (dipanggil Action setelah kode-sementara
     * sehingga swap valid lolos, duplikat nyata tetap 422). Gagal melempar
     * ValidationException sehingga pemanggil HTTP tetap merespons 422.
     *
     * @param  array<string, mixed>  $item
     */
    private function validasiSintaks(string $indikatorId, array $item): void
    {
        $validator = Validator::make($item, $this->aturanItem($indikatorId), $this->pesanItem());
        $validator->after(function (ValidatorContract $v) use ($item): void {
            $this->tambahErrorPenyebutBilaNol($v, $item, 'bobot');
        });
        $validator->validate();
    }

    /**
     * Menjalankan validator sintaks kandidat tanpa cek unique-vs-DB-lama.
     *
     * Dipakai `modelKandidat`/jalur kandidat transisi formula agar baris
     * baru yang memakai ulang kode yang dibebaskan baris existing pada
     * payload yang sama tidak ditolak palsu. Duplikat DALAM payload tetap
     * ditolak via `distinct`; duplikat final-set dan constraint DB
     * ditegakkan pemanggil. Gagal melempar ValidationException (422).
     *
     * @param  array<string, mixed>  $item
     */
    public function validasiSintaksKandidat(array $item, ?string $prefix = null): void
    {
        $validator = Validator::make($item, $this->aturanSintaksItem(), $this->pesanItem());
        $validator->after(function (ValidatorContract $v) use ($item): void {
            $this->tambahErrorPenyebutBilaNol($v, $item, 'bobot');
        });
        if ($validator->fails() && $prefix !== null) {
            $errors = [];
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors[$prefix.'.'.$field] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
        $validator->validate();
    }

    /**
     * Normalisasi satu baris input komponen sebelum disimpan atau dinilai sebagai kandidat.
     *
     * @param  array<string, mixed>  $item
     * @return array{kode: string, label: string, peran: mixed, bobot: mixed, urutan: int, satuan: ?string, aktif: bool}
     */
    public function normalisasiInput(array $item): array
    {
        $kode = trim((string) ($item['kode'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        $satuan = null;
        if (isset($item['satuan']) && $item['satuan'] !== null) {
            $satuanTrim = trim((string) $item['satuan']);
            $satuan = $satuanTrim !== '' ? $satuanTrim : null;
        }

        return [
            'kode' => $kode,
            'label' => $label,
            'peran' => $item['peran'] ?? null,
            'bobot' => $item['bobot'] ?? 0,
            'urutan' => (int) ($item['urutan'] ?? 1),
            'satuan' => $satuan,
            'aktif' => array_key_exists('aktif', $item) ? (bool) $item['aktif'] : true,
        ];
    }

    /**
     * Atribut siap simpan untuk satu baris komponen.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function atributCreate(string $indikatorId, array $item, string $createdBy): array
    {
        return array_merge($this->normalisasiInput($item), [
            'indikator_id' => $indikatorId,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Model kandidat in-memory untuk penilaian komposisi akhir sebelum mutasi apa pun.
     *
     * SENGAJA hanya validasi sintaks kandidat (tanpa unique-vs-DB-lama) agar
     * tidak menolak karena row existing masih berkode lama; unique final-set
     * dan constraint DB ditegakkan pemanggil.
     *
     * @param  array<string, mixed>  $item
     */
    public function modelKandidat(string $indikatorId, array $item, ?string $prefix = null): IndikatorKomponen
    {
        $this->validasiSintaksKandidat($item, $prefix);

        return new IndikatorKomponen($this->atributCreate($indikatorId, $item, ''));
    }

    /**
     * Membuat satu baris komponen tervalidasi sintaks penuh di dalam transaksi pemanggil.
     *
     * Validasi mencakup unique-vs-DB sebagai penjaga saat persist sehingga
     * tidak ada jalur `buat()` yang lolos sintaks. Pemanggil tetap memegang
     * kunci baris induk dan urutan kunci global; metode ini tidak membuka
     * transaksi sendiri agar tidak memecah atomicity.
     *
     * @param  array<string, mixed>  $item
     */
    public function buat(string $indikatorId, array $item, string $createdBy): IndikatorKomponen
    {
        $this->validasiSintaks($indikatorId, $item);
        $komponen = IndikatorKomponen::create($this->atributCreate($indikatorId, $item, $createdBy));

        $segar = $komponen->fresh();

        return $segar ?? $komponen;
    }

    /**
     * Pesan tunggal untuk kode komponen yang sudah dipakai pada indikator yang sama.
     */
    public function pesanKodeDuplikat(): string
    {
        return 'Kode komponen sudah digunakan pada indikator ini.';
    }

    /**
     * Pesan tunggal untuk bobot penyebut yang tidak positif.
     */
    public function pesanBobotPenyebut(): string
    {
        return 'Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.';
    }

    /**
     * Membentuk snapshot audit dengan bobot eksak sebagai string tanpa pembulatan biner.
     *
     * @return array<string, mixed>
     */
    public function formatAuditSnapshot(IndikatorKomponen $komponen): array
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

    /** Hanya FK domain komponen; kegagalan audit/koneksi lain tidak disamarkan. */
    public function isReferenceConstraintViolation(QueryException $exception): bool
    {
        if ((string) $exception->getCode() !== '23503') {
            return false;
        }
        foreach (['jadwal_snapshot_komponen', 'rencana_aksi_target', 'pengukuran_komponen', 'klaim_kegiatan'] as $table) {
            if (str_contains($exception->getMessage(), $table.'_komponen_id_foreign')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mengecek pelanggaran unique constraint pada kode komponen PostgreSQL.
     */
    public function isUniqueConstraintViolation(QueryException $e): bool
    {
        return (string) $e->getCode() === '23505'
            && str_contains($e->getMessage(), 'indikator_komponen_indikator_id_kode_unique');
    }
}
