<?php

namespace App\Http\Requests\Kegiatan;

use App\Actions\Kegiatan\CatatRealisasiKegiatan;
use App\Models\Kegiatan;
use App\Policies\KegiatanPolicy;
use App\Support\PermissionDecision;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Validasi bentuk data pelaksanaan (Q69 §69.3 butir 4, Q74 §74.2). Kewajiban isian saat terlaksana, rentang
 * tanggal periode, status awal, dan gerbang bukti diperiksa Action di bawah lock. Setiap penolakan diaudit.
 */
class CatatRealisasiKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $keputusan = null;

    public function authorize(): bool
    {
        $kegiatan = $this->kegiatan();
        $this->keputusan = app(KegiatanPolicy::class)->keputusanSimpan($this->user(), $kegiatan, (string) $kegiatan->unit_id);
        if (($this->keputusan->basis['alasan'] ?? null) === 'inactive_unit') {
            app(CatatRealisasiKegiatan::class)->catatPenolakan($this->user(), $kegiatan, 'Unit kegiatan harus tetap aktif.', $this->keputusan);
            throw ValidationException::withMessages(['status' => 'Unit kegiatan harus tetap aktif.']);
        }

        return $this->keputusan->allowed;
    }

    protected function failedAuthorization(): void
    {
        app(CatatRealisasiKegiatan::class)->catatPenolakan($this->user(), $this->kegiatan(), 'Izin kegiatan tidak tersedia atau telah dicabut.', $this->keputusan);
        parent::failedAuthorization();
    }

    protected function failedValidation(Validator $validator): void
    {
        app(CatatRealisasiKegiatan::class)->catatPenolakan($this->user(), $this->kegiatan(), (string) $validator->errors()->first(), $this->keputusan);
        parent::failedValidation($validator);
    }

    public function rules(): array
    {
        $teks = ['nullable', 'string', 'max:10000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'];

        return [
            'tanggal_realisasi' => ['nullable', 'date_format:Y-m-d'],
            'biaya_terpakai' => ['nullable', 'integer', 'between:0,999999999999999'],
            'realisasi_peserta' => ['nullable', 'integer', 'between:0,2147483647'],
            'uraian_pelaksanaan' => $teks,
            'kendala' => $teks,
            'strategi_tindaklanjut' => $teks,
            'tandai_terlaksana' => ['sometimes', 'boolean'],
            'expected_state' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_realisasi.date_format' => 'Tanggal realisasi harus berformat tanggal yang valid.',
            'biaya_terpakai.integer' => 'Biaya terpakai harus berupa rupiah bulat tanpa sen.',
            'biaya_terpakai.between' => 'Biaya terpakai harus rupiah nonnegatif paling banyak 15 digit.',
            'realisasi_peserta.integer' => 'Peserta riil harus berupa jumlah bulat.',
            'realisasi_peserta.between' => 'Peserta riil harus berupa jumlah nonnegatif yang dapat disimpan.',
            'expected_state.required' => 'Muat ulang kegiatan sebelum mencatat realisasi.',
            '*.not_regex' => 'Teks mengandung karakter kontrol yang tidak dapat disimpan.',
        ];
    }

    private function kegiatan(): Kegiatan
    {
        /** @var Kegiatan */
        return $this->route('kegiatan');
    }
}
