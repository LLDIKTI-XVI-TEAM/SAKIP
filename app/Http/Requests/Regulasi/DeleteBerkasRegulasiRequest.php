<?php

namespace App\Http\Requests\Regulasi;

use App\Models\Berkas;
use App\Models\Regulasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteBerkasRegulasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $regulasi = $this->route('regulasi');
        $berkas = $this->route('berkas');

        return $regulasi instanceof Regulasi
            && $berkas instanceof Berkas
            && $berkas->berkasable_type === $regulasi->getMorphClass()
            && $berkas->berkasable_id === $regulasi->id
            && Gate::allows('deleteAttachment', [$regulasi, $berkas]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['alasan' => 'alasan audit'];
    }
}
