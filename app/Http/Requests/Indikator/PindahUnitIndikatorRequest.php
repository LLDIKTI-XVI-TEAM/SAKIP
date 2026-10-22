<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Support\AuditReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PindahUnitIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var IndikatorKinerja|null $indikator */
        $indikator = $this->route('indikator');

        return $indikator !== null && Gate::allows('update', $indikator);
    }

    /**
     * Aturan validasi pemindahan unit penanggung jawab indikator.
     *
     * Unit tujuan wajib berstatus aktif (unit lama boleh nonaktif karena
     * perpindahan justru melepaskan kepemilikan lama). Alasan opsional
     * (Q43 §43.3 butir 3) dan disanitasi bila diisi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'uuid',
                Rule::exists('unit', 'id')->where('status', 'aktif'),
            ],
            'alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => 'Unit penanggung jawab tujuan wajib dipilih.',
            'unit_id.exists' => 'Unit penanggung jawab tujuan tidak valid atau sudah nonaktif.',
            'alasan.max' => 'Alasan pemindahan unit penanggung jawab maksimal 1.000 karakter.',
        ];
    }
}
