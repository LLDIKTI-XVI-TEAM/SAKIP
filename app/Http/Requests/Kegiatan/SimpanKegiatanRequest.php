<?php

namespace App\Http\Requests\Kegiatan;

use App\Actions\Kegiatan\SimpanKegiatan;
use App\Models\Kegiatan;
use App\Policies\KegiatanPolicy;
use App\Support\PermissionDecision;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Validasi hanya data rencana; field anggaran dan pelaksanaan sengaja tidak menjadi input bisnis. */
class SimpanKegiatanRequest extends FormRequest
{
    private ?PermissionDecision $keputusan = null;

    public function authorize(): bool
    {
        $kegiatan = $this->route('kegiatan');
        $unitId = $this->input('unit_id');
        // Input UUID rusak masuk validasi shape; tidak pernah dipakai sebagai query PostgreSQL.
        if (! is_string($unitId) || ! Str::isUuid($unitId)) {
            if ($kegiatan instanceof Kegiatan) {
                $this->keputusan = app(KegiatanPolicy::class)->keputusanSimpan($this->user(), $kegiatan, (string) $kegiatan->unit_id);

                return $this->keputusan->allowed;
            }

            return true;
        }
        $this->keputusan = app(KegiatanPolicy::class)->keputusanSimpan($this->user(), $kegiatan instanceof Kegiatan ? $kegiatan : null, $unitId);
        if (($this->keputusan->basis['alasan'] ?? $this->keputusan->basis['tujuan']['alasan'] ?? null) === 'inactive_unit') {
            throw ValidationException::withMessages(['unit_id' => 'Pilih unit yang masih aktif.']);
        }

        return $this->keputusan->allowed;
    }

    /** Penolakan awal diaudit sekali dengan keputusan yang sama; Policy tetap tanpa efek samping. */
    protected function failedAuthorization(): void
    {
        $kegiatan = $this->route('kegiatan');
        if ($this->keputusan) {
            app(SimpanKegiatan::class)->catatPenolakan(
                $this->user(), $kegiatan instanceof Kegiatan ? $kegiatan : null, $this->input('unit_id'),
                'Izin pencatatan rencana kegiatan tidak tersedia atau telah dicabut.', $this->keputusan,
            );
        }
        parent::failedAuthorization();
    }

    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'uuid', Rule::exists('unit', 'id')->where('status', 'aktif')],
            'tahun' => ['required', 'integer', 'between:1,9999'],
            'periode_id' => ['required', 'uuid', Rule::exists('periode', 'id')->where('aktif', true)],
            'nama' => ['required', 'string', 'max:255', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'tujuan' => ['required', 'string', 'max:10000', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'sasaran_peserta' => ['nullable', 'integer', 'between:0,2147483647'],
            'keterangan_peserta' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'lokasi' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u'],
            'tanggal_rencana' => ['nullable', 'date_format:Y-m-d'],
            'expected_state' => [$this->route('kegiatan') ? 'required' : 'nullable', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'unit_id.exists' => 'Pilih unit yang masih aktif.',
            'periode_id.exists' => 'Pilih master periode yang masih aktif.',
            'nama.required' => 'Nama kegiatan wajib diisi.',
            'tujuan.required' => 'Tujuan kegiatan wajib diisi.',
            'sasaran_peserta.between' => 'Sasaran peserta harus berupa jumlah nonnegatif yang dapat disimpan.',
            'expected_state.required' => 'Muat ulang kegiatan sebelum menyimpan perubahan.',
            '*.not_regex' => 'Teks mengandung karakter kontrol yang tidak dapat disimpan.',
        ];
    }
}
