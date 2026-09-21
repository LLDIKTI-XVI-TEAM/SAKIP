<?php

namespace App\Http\Requests;

use App\Services\Authorization\PermissionResolver;
use Illuminate\Foundation\Http\FormRequest;

class DeleteJenisBerkasRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user()?->fresh();

        return $user !== null && app(PermissionResolver::class)->allows($user, 'jenis_berkas:delete');
    }

    public function rules(): array
    {
        return [
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
