<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', IndikatorKinerja::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sasaran_strategis_id' => ['required', 'uuid', 'exists:sasaran_strategis,id'],
            'kode' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:1000'],
            'definisi_operasional' => ['nullable', 'string', 'max:2000'],
            'satuan' => ['required', 'string', 'max:50'],
            'unit_id' => ['required', 'uuid', 'exists:unit,id'],
            'arah' => ['required', 'in:naik_baik,turun_baik'],
            'tipe_perhitungan' => ['required', 'in:manual,rasio_persen,penjumlahan'],
            'presisi' => ['nullable', 'integer', 'between:0,4'],
            'desimal_tampilan' => ['nullable', 'integer', 'between:0,4'],
            'wajib_catatan' => ['nullable', 'boolean'],
            'jenis_agregasi' => ['nullable', 'string', 'max:50'],
            'regulasi_id' => ['nullable', 'uuid', 'exists:regulasi,id'],
            'is_aktif' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sasaran_strategis_id.required' => 'Sasaran strategis wajib dipilih.',
            'sasaran_strategis_id.exists' => 'Sasaran strategis yang dipilih tidak valid.',
            'kode.required' => 'Kode indikator kinerja wajib diisi.',
            'nama.required' => 'Nama indikator kinerja wajib diisi.',
            'satuan.required' => 'Satuan indikator kinerja wajib diisi.',
            'unit_id.required' => 'Unit penanggung jawab wajib dipilih.',
            'unit_id.exists' => 'Unit penanggung jawab tidak valid.',
            'arah.required' => 'Arah penilaian wajib dipilih.',
            'arah.in' => 'Arah penilaian harus berupa naik_baik atau turun_baik.',
            'tipe_perhitungan.required' => 'Tipe perhitungan wajib dipilih.',
            'tipe_perhitungan.in' => 'Tipe perhitungan harus berupa manual, rasio_persen, atau penjumlahan.',
            'regulasi_id.exists' => 'Rujukan regulasi tidak valid.',
        ];
    }
}
