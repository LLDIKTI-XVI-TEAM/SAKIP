<?php

namespace App\Http\Requests\Periode;

use App\Http\Requests\PeriodeJadwalMutationRequest;
use App\Support\PermissionCodes;

class ReplaceFinalPeriodeRequest extends PeriodeJadwalMutationRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::PERIODE_UPDATE;
    }

    public function auditEvent(): string
    {
        return 'periode.ganti_nilai_akhir';
    }

    public function rules(): array
    {
        return ['periode_lama_id' => ['required', 'uuid'], 'periode_pengganti_id' => ['required', 'uuid', 'different:periode_lama_id'],
            'revisi_lama' => ['required', 'integer', 'min:1', 'max:2147483647'], 'revisi_pengganti' => ['required', 'integer', 'min:1', 'max:2147483647']];
    }
}
