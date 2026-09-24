<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreIndikatorKomponenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'komponen:create');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $decision = app(PermissionResolver::class)->decide($user, 'komponen:create');
            $rawAlasan = $this->input('alasan');
            $kode = is_string($this->input('kode')) ? trim($this->input('kode')) : '';
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan penambahan komponen indikator ditolak karena tidak memiliki izin.'.($kode !== '' ? ' (Kode: '.$kode.')' : '');

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'komponen.buat_ditolak',
                objekTipe: 'indikator_komponen',
                objekId: (string) Str::uuid(),
                alasan: $alasan,
                dasarIzin: $decision,
            );
        }

        parent::failedAuthorization();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $indikator = $this->route('indikator');
        $indikatorId = $indikator instanceof IndikatorKinerja ? $indikator->id : $indikator;

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('indikator_komponen', 'kode')->where(fn ($query) => $query->where('indikator_id', $indikatorId)),
            ],
            'label' => ['required', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'pengurang', 'penjumlah', 'faktor'])],
            'bobot' => ['required', 'numeric', 'min:0'],
            'urutan' => ['required', 'integer', 'min:1'],
            'aktif' => ['sometimes', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode komponen wajib diisi.',
            'kode.max' => 'Kode komponen maksimal 50 karakter.',
            'kode.regex' => 'Kode komponen hanya boleh berisi huruf, angka, dan garis bawah (_).',
            'kode.unique' => 'Kode komponen sudah digunakan pada indikator ini.',
            'label.required' => 'Label komponen wajib diisi.',
            'label.max' => 'Label komponen maksimal 255 karakter.',
            'peran.required' => 'Peran komponen wajib dipilih.',
            'peran.in' => 'Peran komponen harus salah satu dari: pembilang, penyebut, pengurang, penjumlah, faktor.',
            'bobot.required' => 'Bobot komponen wajib diisi.',
            'bobot.numeric' => 'Bobot komponen harus berupa angka numerik.',
            'bobot.min' => 'Bobot komponen minimal bernilai 0.',
            'urutan.required' => 'Urutan komponen wajib diisi.',
            'urutan.integer' => 'Urutan komponen harus berupa bilangan bulat.',
            'urutan.min' => 'Urutan komponen minimal 1.',
        ];
    }
}
