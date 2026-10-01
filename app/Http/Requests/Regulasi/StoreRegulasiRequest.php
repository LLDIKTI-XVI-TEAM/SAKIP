<?php

namespace App\Http\Requests\Regulasi;

use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;

/** Membuat induk beserta lampiran dengan izin regulasi:create; versi berasal dari server. */
class StoreRegulasiRequest extends RegulasiMutationRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Regulasi::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->mutationRules();
    }
}
