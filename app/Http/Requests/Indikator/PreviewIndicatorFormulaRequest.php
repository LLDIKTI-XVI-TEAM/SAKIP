<?php

namespace App\Http\Requests\Indikator;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class PreviewIndicatorFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(PermissionResolver::class)->resolve($this->user(), 'komponen:read')->allowed;
    }

    public function rules(): array
    {
        return [
            'expected_updated_at' => ['required', 'date'],
            'values' => ['present', 'array'],
            'values.*' => ['nullable', 'string', 'max:32', 'regex:/^-?\d+(?:\.\d{1,12})?$/'],
            'nilai_manual' => ['nullable', 'string', 'max:32', 'regex:/^-?\d+(?:\.\d{1,12})?$/'],
        ];
    }
}
