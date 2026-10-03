<?php

namespace App\Http\Requests\Periode;

use App\Support\PermissionCodes;

class UpdatePeriodeRequest extends StorePeriodeRequest
{
    public function permissionCode(): string
    {
        return PermissionCodes::PERIODE_UPDATE;
    }

    public function auditEvent(): string
    {
        return 'periode.ubah';
    }

    public function rules(): array
    {
        return self::inputRules(true);
    }
}
