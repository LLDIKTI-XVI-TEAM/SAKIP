<?php

namespace App\Http\Requests\Pengukuran;

use Illuminate\Support\Arr;

class PreviewPengukuranRequest extends SavePengukuranRequest
{
    public function rules(): array
    {
        return Arr::only(parent::rules(), ['komponen', 'komponen.*', 'komponen.*.komponen_id', 'komponen.*.nilai']);
    }
}
