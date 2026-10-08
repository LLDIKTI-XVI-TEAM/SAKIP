<?php

namespace App\Http\Requests\PenanggungJawab;

use App\Models\IndikatorKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $indicator = $this->route('indikator');

        return $indicator instanceof IndikatorKinerja && Gate::allows('assignPenanggungJawab', $indicator);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid'],
            'tanggal_mulai_berlaku' => ['required', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'expected_state' => ['required', 'string', 'size:64'],
            // Required bersyarat diperiksa sesudah lock agar penolakan pergantian diaudit.
            'alasan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih pengguna aktif sebagai penanggung jawab.',
            'user_id.uuid' => 'Identitas pengguna tidak valid.',
            'tanggal_mulai_berlaku.required' => 'Tanggal mulai berlaku wajib diisi.',
            'tanggal_mulai_berlaku.date_format' => 'Tanggal mulai berlaku harus berupa tanggal yang valid.',
            'tanggal_mulai_berlaku.after_or_equal' => 'Tanggal mulai berlaku harus berupa tanggal yang valid.',
            'expected_state.required' => 'Muat data terbaru sebelum menyimpan penugasan.',
            'alasan.max' => 'Alasan maksimal 2.000 karakter.',
        ];
    }
}
