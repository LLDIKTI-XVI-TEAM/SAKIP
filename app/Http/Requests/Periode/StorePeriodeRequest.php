<?php

namespace App\Http\Requests\Periode;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\PermissionCodes;

class StorePeriodeRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::PERIODE_CREATE;
    }

    public function auditEvent(): string
    {
        return 'periode.tambah';
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim($this->input('nama'))]);
        }
    }

    /** @return array<string, mixed> */
    public static function inputRules(bool $updating = false): array
    {
        return ['nama' => ['required', 'string', 'max:255'], 'urutan' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'aktif' => ['required', 'boolean'], 'is_nilai_akhir' => ['required', 'boolean'],
            ...($updating ? ['revisi' => ['required', 'integer', 'min:1', 'max:2147483647']] : [])];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
