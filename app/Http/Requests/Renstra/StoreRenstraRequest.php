<?php

namespace App\Http\Requests\Renstra;

use App\Models\Renstra;
use Illuminate\Support\Facades\Gate;

class StoreRenstraRequest extends RenstraMutationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Renstra::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->mutationRules(requireReason: false);
    }
}
