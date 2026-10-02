<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ChangeIndicatorFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');

        return $indikator !== null && Gate::allows('update', $indikator);
    }

    /**
     * Aturan sintaks transisi formula atomik (validitas domain penuh
     * ditentukan `IndikatorPerhitunganService::validateDefinisiKomponen`
     * di Action, bukan di sini).
     *
     * `komponen` memakai `present` (boleh kosong untuk target manual
     * setelah penonaktifan via Kelola Komponen); kekosongan yang invalid
     * untuk nonmanual ditolak sebagai 422 `tipe_perhitungan` oleh Action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');
        $indikatorId = $indikator?->id;

        return [
            'tipe_perhitungan' => ['required', 'in:manual,rasio_persen,penjumlahan'],
            'komponen' => ['present', 'array', 'max:50'],
            'komponen.*.kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                'distinct',
                Rule::unique('indikator_komponen', 'kode')->where(fn ($query) => $query->where('indikator_id', $indikatorId)),
            ],
            'komponen.*.label' => ['required', 'string', 'max:255'],
            'komponen.*.satuan' => ['nullable', 'string', 'max:50'],
            'komponen.*.peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'penjumlah'])],
            'komponen.*.bobot' => ['required', 'numeric', 'decimal:0,12', 'min:0', 'max:999999999'],
            'komponen.*.urutan' => ['required', 'integer', 'min:1', 'max:32767'],
            'komponen.*.aktif' => ['sometimes', 'boolean'],
            'expected_updated_at' => ['required', 'date'],
        ];
    }

    /**
     * Bobot penyebut wajib > 0 diperiksa per item (padanan closure sintaks
     * StoreIndikatorKomponenRequest untuk payload bersarang).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            /** @var list<array<string, mixed>> $items */
            $items = is_array($this->input('komponen')) ? $this->input('komponen') : [];
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (($item['peran'] ?? null) === 'penyebut') {
                    $bobot = isset($item['bobot']) ? (float) $item['bobot'] : 0.0;
                    if ($bobot <= 0 || round($bobot, 12) <= 0) {
                        $validator->errors()->add(
                            "komponen.{$index}.bobot",
                            'Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.'
                        );
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipe_perhitungan.required' => 'Tipe perhitungan wajib dipilih.',
            'tipe_perhitungan.in' => 'Tipe perhitungan harus berupa manual, rasio_persen, atau penjumlahan.',
            'komponen.present' => 'Konfigurasi komponen wajib disertakan (boleh kosong untuk target manual).',
            'komponen.array' => 'Konfigurasi komponen harus berupa daftar.',
            'komponen.*.kode.required' => 'Kode komponen wajib diisi.',
            'komponen.*.kode.regex' => 'Kode komponen hanya boleh berisi huruf, angka, dan garis bawah (_).',
            'komponen.*.kode.distinct' => 'Kode komponen tidak boleh duplikat dalam satu transisi.',
            'komponen.*.kode.unique' => 'Kode komponen sudah digunakan pada indikator ini.',
            'komponen.*.label.required' => 'Label komponen wajib diisi.',
            'komponen.*.peran.required' => 'Peran komponen wajib dipilih.',
            'komponen.*.peran.in' => 'Peran komponen harus salah satu dari: pembilang, penyebut, penjumlah.',
            'komponen.*.bobot.required' => 'Bobot komponen wajib diisi.',
            'komponen.*.bobot.numeric' => 'Bobot komponen harus berupa angka numerik.',
            'komponen.*.urutan.required' => 'Urutan komponen wajib diisi.',
            'expected_updated_at.required' => 'Timestamp versi wajib disertakan. Muat ulang halaman untuk mendapatkan data terkini.',
            'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
        ];
    }
}
