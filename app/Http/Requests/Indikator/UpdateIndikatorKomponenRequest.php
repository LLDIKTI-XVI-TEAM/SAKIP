<?php

namespace App\Http\Requests\Indikator;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateIndikatorKomponenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'komponen:update');
    }

    protected function failedAuthorization(): void
    {
        $user = $this->user()?->fresh();
        if ($user) {
            $komponen = $this->route('komponen');
            $komponenId = (string) ($komponen instanceof IndikatorKomponen ? $komponen->id : ($komponen ?? Str::uuid()));
            $decision = app(PermissionResolver::class)->decide($user, 'komponen:update');
            $rawAlasan = $this->input('alasan');
            $alasan = is_string($rawAlasan) && trim($rawAlasan) !== ''
                ? trim($rawAlasan)
                : 'Percobaan pembaruan komponen indikator ditolak karena tidak memiliki izin.';

            app(AuditLogger::class)->catat(
                actor: $user,
                tindakan: 'komponen.ubah_ditolak',
                objekTipe: 'indikator_komponen',
                objekId: $komponenId,
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
        $komponen = $this->route('komponen');
        $komponenId = $komponen instanceof IndikatorKomponen ? $komponen->id : $komponen;

        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('indikator_komponen', 'kode')
                    ->where(fn ($query) => $query->where('indikator_id', $indikatorId))
                    ->ignore($komponenId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'satuan' => ['nullable', 'string', 'max:50'],
            'peran' => ['required', 'string', Rule::in(['pembilang', 'penyebut', 'penjumlah'])],
            'bobot' => ['required', 'numeric', 'min:0'],
            'urutan' => ['required', 'integer', 'min:1'],
            'aktif' => ['required', 'boolean'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
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
            'peran.in' => 'Peran komponen harus salah satu dari: pembilang, penyebut, penjumlah.',
            'bobot.required' => 'Bobot komponen wajib diisi.',
            'bobot.numeric' => 'Bobot komponen harus berupa angka numerik.',
            'bobot.min' => 'Bobot komponen minimal bernilai 0.',
            'urutan.required' => 'Urutan komponen wajib diisi.',
            'urutan.integer' => 'Urutan komponen harus berupa bilangan bulat.',
            'urutan.min' => 'Urutan komponen minimal 1.',
            'aktif.required' => 'Status aktif komponen wajib ditentukan.',
            'alasan.required' => 'Alasan perubahan komponen wajib diisi.',
            'alasan.min' => 'Alasan perubahan komponen minimal 5 karakter.',
            'alasan.max' => 'Alasan perubahan komponen maksimal 1000 karakter.',
        ];
    }
}
