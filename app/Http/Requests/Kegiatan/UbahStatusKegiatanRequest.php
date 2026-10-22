<?php

namespace App\Http\Requests\Kegiatan;

use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Models\Kegiatan;
use App\Policies\KegiatanPolicy;
use App\Support\PermissionDecision;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validasi bentuk transisi status pelaksanaan. Justifikasi adalah data domain kegiatan yang dibaca
 * verifikator (Q69 §69.2), bukan alasan audit. Keberadaan dan urutan periode tujuan serta status awal
 * diperiksa Action di bawah lock. Setiap penolakan di sini diaudit (Q69 §69.4).
 */
class UbahStatusKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $keputusan = null;

    public function authorize(): bool
    {
        $kegiatan = $this->kegiatan();
        $this->keputusan = app(KegiatanPolicy::class)->keputusanSimpan($this->user(), $kegiatan, (string) $kegiatan->unit_id);
        if (($this->keputusan->basis['alasan'] ?? null) === 'inactive_unit') {
            app(UbahStatusKegiatan::class)->catatPenolakan($this->user(), $kegiatan, 'Unit kegiatan harus tetap aktif.', $this->keputusan);
            throw ValidationException::withMessages(['status' => 'Unit kegiatan harus tetap aktif.']);
        }

        return $this->keputusan->allowed;
    }

    protected function failedAuthorization(): void
    {
        app(UbahStatusKegiatan::class)->catatPenolakan($this->user(), $this->kegiatan(), 'Izin kegiatan tidak tersedia atau telah dicabut.', $this->keputusan);
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        app(UbahStatusKegiatan::class)->catatPenolakan($this->user(), $this->kegiatan(), (string) $validator->errors()->first(), $this->keputusan);
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['tidak_terlaksana', 'ditunda', 'batal'])],
            'justifikasi' => ['required', 'string', 'max:10000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'periode_tujuan_id' => ['exclude_unless:status,ditunda', 'required', 'uuid'],
            'expected_state' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pilih status tidak terlaksana, ditunda, atau batal.',
            'status.in' => 'Pilih status tidak terlaksana, ditunda, atau batal.',
            'justifikasi.required' => 'Justifikasi wajib diisi.',
            'periode_tujuan_id.required' => 'Pilih periode tujuan.',
            'periode_tujuan_id.uuid' => 'Pilih periode tujuan dari daftar.',
            'expected_state.required' => 'Muat ulang kegiatan sebelum mengubah status.',
            '*.not_regex' => 'Teks mengandung karakter kontrol yang tidak dapat disimpan.',
        ];
    }

    private function kegiatan(): Kegiatan
    {
        /** @var Kegiatan */
        return $this->route('kegiatan');
    }
}
