<?php

namespace App\Http\Requests\Laporan;

use App\Services\Authorization\PermissionResolver;

class EksporRekapIndikatorPeriodeRequest extends RekapIndikatorPeriodeRequest
{
    /** Kedua izin diperiksa sebelum validasi melakukan lookup jadwal/periode. */
    public function authorize(): bool
    {
        return parent::authorize() && app(PermissionResolver::class)->allows($this->user(), 'laporan:ekspor');
    }
}
