<?php

namespace App\Http\Requests\Regulasi;

use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;

class UpdateRegulasiRequest extends RegulasiMutationRequest
{
    public function authorize(): bool
    {
        $regulasi = $this->route('regulasi');

        return $regulasi instanceof Regulasi && Gate::allows('update', $regulasi);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->mutationRules(requireReason: true, requireVersion: true);
    }
}
