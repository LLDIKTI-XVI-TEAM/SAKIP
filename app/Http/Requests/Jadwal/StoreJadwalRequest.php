<?php

namespace App\Http\Requests\Jadwal;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\PermissionCodes;

class StoreJadwalRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::JADWAL_CREATE;
    }

    public function auditEvent(): string
    {
        return 'jadwal.tambah';
    }

    /** @return array<string, mixed> */
    public static function inputRules(bool $updating = false): array
    {
        return [
            'renstra_id' => ['required', 'uuid'],
            // Batas representasi YYYY-MM-DD, termasuk kemungkinan satu tahun berikutnya.
            'tahun' => ['required', 'integer', 'between:1,9998'],
            'rencana_aksi_mulai' => ['required', 'date_format:Y-m-d'],
            'rencana_aksi_selesai' => ['required', 'date_format:Y-m-d'],
            'penutupan' => ['required', 'date_format:Y-m-d'],
            // Kunci jumlah periode di sumbernya (maksimal 12 periode
            // kalender) agar batas `targets` max:600 (= 50 komponen × 12 periode)
            // selalu cukup; 13×50=650 tidak lagi dapat terbentuk dari UI.
            'periode' => ['required', 'array', 'list', 'min:1', 'max:12'],
            'periode.*' => ['required', 'array:periode_id,periode_revisi,pengisian_mulai,pengisian_selesai,reviu_mulai,reviu_selesai'],
            'periode.*.periode_id' => ['required', 'uuid', 'distinct:ignore_case'],
            'periode.*.periode_revisi' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'periode.*.pengisian_mulai' => ['required', 'date_format:Y-m-d'],
            'periode.*.pengisian_selesai' => ['required', 'date_format:Y-m-d'],
            'periode.*.reviu_mulai' => ['required', 'date_format:Y-m-d'],
            'periode.*.reviu_selesai' => ['required', 'date_format:Y-m-d'],
            ...($updating ? ['revisi' => ['required', 'integer', 'min:1', 'max:2147483647']] : []),
        ];
    }

    public function rules(): array
    {
        return self::inputRules();
    }
}
