<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');

        return $indikator !== null && Gate::allows('update', $indikator);
    }

    /**
     * Jalur edit umum tidak boleh memindahkan unit penanggung jawab:
     * `unit_id` wajib sama dengan baris yang tersimpan. Pemindahan unit
     * hanya dilayani endpoint pindah-unit khusus.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');

        return [
            'sasaran_strategis_id' => [
                'required',
                'uuid',
                Rule::exists('sasaran_strategis', 'id')->where(function ($query) use ($indikator) {
                    if ($indikator) {
                        $currentRenstraId = DB::table('sasaran_strategis')
                            ->where('id', $indikator->sasaran_strategis_id)
                            ->value('renstra_id');
                        if ($currentRenstraId) {
                            $query->where('renstra_id', $currentRenstraId);
                        }
                    }
                }),
            ],
            'kode' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:1000'],
            'definisi_operasional' => ['nullable', 'string', 'max:2000'],
            'satuan' => ['required', 'string', 'max:50'],
            'unit_id' => [
                'required',
                'uuid',
                Rule::in($indikator ? [$indikator->unit_id] : []),
            ],
            'arah' => ['required', 'in:naik_baik,turun_baik'],
            'tipe_perhitungan' => ['required', 'in:manual,rasio_persen,penjumlahan'],
            'presisi' => ['nullable', 'integer', 'between:0,4'],
            'desimal_tampilan' => ['nullable', 'integer', 'between:0,4'],
            'wajib_catatan' => ['nullable', 'boolean'],
            'regulasi_id' => ['nullable', 'uuid', Rule::exists('regulasi', 'id')->where('aktif', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sasaran_strategis_id.required' => 'Sasaran strategis wajib dipilih.',
            'sasaran_strategis_id.exists' => 'Sasaran strategis yang dipilih tidak valid atau berada di luar Renstra asal.',
            'kode.required' => 'Kode indikator kinerja wajib diisi.',
            'nama.required' => 'Nama indikator kinerja wajib diisi.',
            'satuan.required' => 'Satuan indikator kinerja wajib diisi.',
            'unit_id.required' => 'Unit penanggung jawab wajib dipilih.',
            'unit_id.in' => 'Unit penanggung jawab tidak dapat diubah melalui edit umum. Gunakan endpoint pindah unit khusus untuk memindahkan indikator ke unit lain.',
            'arah.required' => 'Arah penilaian wajib dipilih.',
            'arah.in' => 'Arah penilaian harus berupa naik_baik atau turun_baik.',
            'tipe_perhitungan.required' => 'Tipe perhitungan wajib dipilih.',
            'tipe_perhitungan.in' => 'Tipe perhitungan harus berupa manual, rasio_persen, atau penjumlahan.',
            'regulasi_id.exists' => 'Rujukan regulasi tidak valid atau sudah nonaktif.',
        ];
    }
}
