<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use Illuminate\Support\Facades\Gate;

class UpdateRenstraRequest extends RenstraMutationRequest
{
    public function authorize(): bool
    {
        $renstra = $this->route('renstra');

        return $renstra instanceof Renstra
            ? Gate::allows('update', $renstra)
            : false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $renstra = $this->route('renstra');
        $isAktif = $renstra instanceof Renstra && ($renstra->status === Renstra::STATUS_AKTIF || $renstra->is_aktif);

        return $this->mutationRules(requireReason: $isAktif);
    }
}
