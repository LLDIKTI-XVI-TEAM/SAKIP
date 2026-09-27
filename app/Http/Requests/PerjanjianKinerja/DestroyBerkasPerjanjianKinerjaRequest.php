<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\RenstraPk;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasPerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');

        return $pk instanceof RenstraPk
            ? ($this->user()?->can('deleteBerkas', $pk) ?? false)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
