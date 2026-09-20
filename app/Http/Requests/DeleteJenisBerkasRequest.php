<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('jenis_berkas:delete');
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
