<?php

namespace App\Http\Requests\PenanggungJawab;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class QueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('penanggung_jawab:update');
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'tanggal_acuan' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:0001-01-01'],
            'unit_id' => ['nullable', 'uuid'],
            'user_id' => [$this->routeIs('penanggung-jawab.readiness') ? 'required' : 'nullable', 'uuid'],
            'after' => ['nullable', 'uuid'],
            'after_scope' => ['nullable', 'string', 'size:64', 'regex:/\A[0-9a-f]{64}\z/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
