<?php

namespace App\Services\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as ValidatorContract;

/**
 * Layanan mutation komponen yang dipakai bersama CRUD dan transisi formula atomik.
 *
 * Menyatukan normalisasi (trim kode/label/satuan, bawaan aktif true), pemeriksaan
 * sintaks bobot penyebut, snapshot audit bobot-eksak-string, dan pemetaan pelanggaran
 * unique menjadi pesan kode agar kedua jalur tidak drift.
 *
 * Penentu akhir validitas domain tetap `IndikatorPerhitunganService::validateDefinisiKomponen`
 * yang dipanggil helper kandidat di batas mutation; tidak ada validator domain kedua.
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
            throw ValidationException::withMessages([$key => $validasi['messages']]);
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
     * Aturan sintaks tunggal untuk satu baris komponen (sumber bersama
     * StoreIndikatorKomponenRequest dan ChangeIndicatorFormulaRequest).
     *
     * Bentuk error key flat (`kode`, `bobot`, ...) dipertahankan untuk jalur
     * normal; varian bersarang memakai prefix `komponen.*.` tanpa mengubah
     * teks pesan. `distinct` hanya untuk payload daftar (transisi formula).
     * `$abaikanId` mengabaikan baris itu sendiri pada cek unique agar
     * penggantian atomik yang mempertahankan kode tidak ditolak palsu;
     * tanpa itu perilaku sama seperti sebelumnya.
     *
     * @return array<string, mixed>
     */
    public function aturanItem(?string $indikatorId, bool $denganDistinct = false, ?string $abaikanId = null): array
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
        $unik = Rule::unique('indikator_komponen', 'kode')->where(fn ($query) => $query->where('indikator_id', $indikatorId));
        if ($abaikanId !== null && $abaikanId !== '') {
            $unik->ignore($abaikanId);
        }
        $kode[] = $unik;

        return [
            'kode' => $kode,
            'label' => ['required', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'penjumlah'])],
            'bobot' => ['required', 'numeric', 'decimal:0,12', 'min:0', 'max:999999999'],
            'urutan' => ['required', 'integer', 'min:1', 'max:32767'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Aturan sintaks per item untuk payload transisi formula.
     *
     * Kunci memakai bentuk `komponen.*.field` agar error key kontrak
     * frontend (`komponen.N.field`) tidak berubah.
     *
     * @return array<string, mixed>
     */
    public function aturanBersarang(?string $indikatorId): array
    {
        $dasar = $this->aturanItem($indikatorId, true);
        $hasil = [];
        foreach ($dasar as $field => $rules) {
            $hasil["komponen.*.{$field}"] = $rules;
        }

        return $hasil;
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
        if (($item['peran'] ?? null) !== 'penyebut') {
            return;
        }

        $bobot = isset($item['bobot']) ? (float) $item['bobot'] : 0.0;
        if ($bobot <= 0 || round($bobot, 12) <= 0) {
            $validator->errors()->add($key, $this->pesanBobotPenyebut());
        }
    }

    /**
     * Menjalankan validator sintaks lengkap untuk satu baris komponen.
     *
     * Dipakai jalur internal (buat/modelKandidat) agar tidak ada penyimpanan
     * yang lolos sintaks bila FormRequest dilewati. Gagal melempar
     * ValidationException sehingga pemanggil HTTP tetap merespons 422.
     *
     * @param  array<string, mixed>  $item
     */
    public function validasiSintaks(string $indikatorId, array $item, ?string $abaikanId = null): void
    {
        $validator = Validator::make($item, $this->aturanItem($indikatorId, false, $abaikanId), $this->pesanItem());
        $validator->after(function (ValidatorContract $v) use ($item): void {
            $this->tambahErrorPenyebutBilaNol($v, $item, 'bobot');
        });
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
    public function atributCreate(string $indikatorId, array $item, string $createdBy): array
    {
        return array_merge($this->normalisasiInput($item), [
            'indikator_id' => $indikatorId,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Model kandidat in-memory untuk penilaian komposisi akhir sebelum mutasi apa pun.
     *
     * @param  array<string, mixed>  $item
     */
    public function modelKandidat(string $indikatorId, array $item, ?string $abaikanId = null): IndikatorKomponen
    {
        $this->validasiSintaks($indikatorId, $item, $abaikanId);

        return new IndikatorKomponen($this->atributCreate($indikatorId, $item, ''));
    }

    /**
     * Membuat satu baris komponen tervalidasi sintaks di dalam transaksi pemanggil.
     *
     * Pemanggil tetap memegang kunci baris induk dan urutan kunci global; metode ini
     * tidak membuka transaksi sendiri agar tidak memecah atomicity.
     *
     * @param  array<string, mixed>  $item
     */
    public function buat(string $indikatorId, array $item, string $createdBy): IndikatorKomponen
    {
        $this->validasiSintaks($indikatorId, $item);
        $normal = $this->normalisasiInput($item);

        $komponen = IndikatorKomponen::create(array_merge($normal, [
            'indikator_id' => $indikatorId,
            'created_by' => $createdBy,
        ]));

        $segar = $komponen->fresh();

        return $segar ?? $komponen;
    }

    /**
     * Memastikan komponen penyebut memiliki bobot lebih besar dari nol.
     *
     * Cermin aturan sintaks pada kedua FormRequest agar pesan identik di semua jalur.
     *
     * @param  array<string, mixed>  $item
     */
    public function pastikanBobotPenyebutValid(array $item, string $key = 'bobot'): void
    {
        if (($item['peran'] ?? null) !== 'penyebut') {
            return;
        }

        $bobot = isset($item['bobot']) ? (float) $item['bobot'] : 0.0;
        if ($bobot <= 0 || round($bobot, 12) <= 0) {
            throw ValidationException::withMessages([
                $key => $this->pesanBobotPenyebut(),
            ]);
        }
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

    /**
     * Mengecek pelanggaran unique constraint pada kode komponen lintas driver basis data.
     */
    public function isUniqueConstraintViolation(QueryException $e): bool
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
