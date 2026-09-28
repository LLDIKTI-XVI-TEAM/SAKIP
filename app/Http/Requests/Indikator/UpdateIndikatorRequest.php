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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');
        $isUnitChanging = $indikator && $this->filled('unit_id') && $indikator->unit_id !== $this->input('unit_id');

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
                Rule::exists('unit', 'id')->where(function ($query) use ($indikator) {
                    // Jika tidak memindahkan kepemilikan unit (unit_id sama dengan eksisting), izinkan unit saat ini
                    if ($indikator && $indikator->unit_id === $this->input('unit_id')) {
                        return;
                    }
                    $query->where('status', 'aktif');
                }),
            ],
            'alasan_pindah_unit' => [
                Rule::requiredIf($isUnitChanging && ! $this->filled('alasan')),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
            'alasan' => [
                Rule::requiredIf($isUnitChanging && ! $this->filled('alasan_pindah_unit')),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
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
            'sasaran_strategis_id.exists' => 'Sasaran strategis yang dipilih tidak valid atau berada di luar Renstra asal.',
            'kode.required' => 'Kode indikator kinerja wajib diisi.',
            'nama.required' => 'Nama indikator kinerja wajib diisi.',
            'satuan.required' => 'Satuan indikator kinerja wajib diisi.',
            'unit_id.required' => 'Unit penanggung jawab wajib dipilih.',
            'unit_id.exists' => 'Unit penanggung jawab tidak valid atau sudah nonaktif.',
            'alasan_pindah_unit.required' => 'Perpindahan unit penanggung jawab memerlukan alasan minimal 10 karakter.',
            'alasan_pindah_unit.min' => 'Alasan perpindahan unit penanggung jawab minimal 10 karakter.',
            'alasan.required' => 'Perpindahan unit penanggung jawab memerlukan alasan minimal 10 karakter.',
            'alasan.min' => 'Alasan perpindahan unit penanggung jawab minimal 10 karakter.',
            'arah.required' => 'Arah penilaian wajib dipilih.',
            'arah.in' => 'Arah penilaian harus berupa naik_baik atau turun_baik.',
            'tipe_perhitungan.required' => 'Tipe perhitungan wajib dipilih.',
            'tipe_perhitungan.in' => 'Tipe perhitungan harus berupa manual, rasio_persen, atau penjumlahan.',
            'regulasi_id.exists' => 'Rujukan regulasi tidak valid.',
        ];
    }
}
