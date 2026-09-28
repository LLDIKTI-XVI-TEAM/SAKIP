<?php

namespace App\Http\Requests\PerjanjianKinerja;

use App\Models\Berkas;
use App\Models\RenstraPk;
use Illuminate\Foundation\Http\FormRequest;

class DestroyBerkasPerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pk = $this->route('perjanjian_kinerja');
        $berkas = $this->route('berkas');

        return $pk instanceof RenstraPk
            && $berkas instanceof Berkas
            && in_array($berkas->berkasable_type, ['renstra_pk', RenstraPk::class], true)
            && $berkas->berkasable_id === $pk->id
            && ($this->user()?->can('deleteBerkas', $pk) ?? false);
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
