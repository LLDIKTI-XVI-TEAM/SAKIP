<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Services\Kinerja\KomponenMutationService;
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
     * `komponen` adalah daftar akhir; ID existing harus milik indikator.
     * Child yang tidak disertakan dinonaktifkan oleh Action. Kekosongan
     * nonmanual ditolak penentu domain di dalam transaksi.
     *
     * `alasan` adalah rationale operator (pola `UpdateIndikatorKomponenRequest`:
     * wajib min 5); sanitasi + batas tulis didelegasikan ke boundary audit
     * (`AuditLogger`/`WriteAuditLog`), bukan duplikat helper di sini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipe_perhitungan' => ['required', 'in:manual,rasio_persen,penjumlahan'],
            'komponen' => ['present', 'array', 'max:50'],
            // Kepemilikan ID diperiksa Action setelah token pada induk terkunci,
            // agar child terhapus tetap menghasilkan konflik untuk formula usang.
            'komponen.*.id' => ['nullable', 'uuid', 'distinct'],
            'komponen.*.kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                'distinct',
            ],
            'komponen.*.label' => ['required', 'string', 'max:255'],
            'komponen.*.satuan' => ['nullable', 'string', 'max:50'],
            'komponen.*.peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'penjumlah'])],
            'komponen.*.bobot' => ['required', 'numeric', 'decimal:0,12', 'min:0', 'max:999999999'],
            'komponen.*.urutan' => ['required', 'integer', 'min:1', 'max:32767'],
            'komponen.*.aktif' => ['sometimes', 'boolean'],
            'expected_updated_at' => ['required', 'date'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * Bobot penyebut wajib > 0 diperiksa per item via layanan bersama agar
     * pesan identik dengan jalur normal (bentuk error key bersarang dipertahankan).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            /** @var list<array<string, mixed>> $items */
            $items = is_array($this->input('komponen')) ? $this->input('komponen') : [];
            $layanan = app(KomponenMutationService::class);
            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $layanan->tambahErrorPenyebutBilaNol($validator, $item, "komponen.{$index}.bobot");
            }
        });
    }

    /**
     * Pesan wrapper dipertahankan di sini; pesan per item delegasi ke peta
     * tunggal layanan agar teks identik dengan jalur normal.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            [
                'tipe_perhitungan.required' => 'Tipe perhitungan wajib dipilih.',
                'tipe_perhitungan.in' => 'Tipe perhitungan harus berupa manual, rasio_persen, atau penjumlahan.',
                'komponen.present' => 'Konfigurasi komponen wajib disertakan (boleh kosong untuk target manual).',
                'komponen.array' => 'Konfigurasi komponen harus berupa daftar.',
                'expected_updated_at.required' => 'Timestamp versi wajib disertakan. Muat ulang halaman untuk mendapatkan data terkini.',
                'expected_updated_at.date' => 'Format timestamp versi tidak valid.',
                'alasan.required' => 'Alasan perubahan formula wajib diisi.',
                'alasan.min' => 'Alasan perubahan formula minimal 5 karakter.',
                'alasan.max' => 'Alasan perubahan formula maksimal 1000 karakter.',
                'komponen.*.id.uuid' => 'Identitas komponen tidak valid.',
                'komponen.*.id.distinct' => 'Identitas komponen tidak boleh duplikat dalam satu transisi.',
            ],
            app(KomponenMutationService::class)->pesanBersarang()
        );
    }
}
