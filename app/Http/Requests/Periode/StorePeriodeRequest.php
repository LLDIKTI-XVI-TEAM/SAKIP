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
            // Hari dan bulan tanpa tahun (Q70 §70.1); kelengkapan, panjang bulan, urutan, dan tumpang tindih diperiksa TanggalPeriodeRules.
            'bulan_mulai' => ['nullable', 'required_if_accepted:aktif', 'integer', 'between:1,12'],
            'hari_mulai' => ['nullable', 'required_if_accepted:aktif', 'integer', 'between:1,31'],
            'bulan_selesai' => ['nullable', 'required_if_accepted:aktif', 'integer', 'between:1,12'],
            'hari_selesai' => ['nullable', 'required_if_accepted:aktif', 'integer', 'between:1,31'],
            ...($updating ? ['revisi' => ['required', 'integer', 'min:1', 'max:2147483647']] : [])];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
