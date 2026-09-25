<?php

namespace App\Http\Requests\Sasaran;

use App\Models\SasaranStrategis;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroySasaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SasaranStrategis|null $sasaran */
        $sasaran = $this->route('sasaran');

        return $sasaran !== null && Gate::allows('delete', $sasaran);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'alasan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
