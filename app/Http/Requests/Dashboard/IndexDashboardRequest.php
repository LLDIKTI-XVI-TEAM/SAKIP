<?php

namespace App\Http\Requests\Dashboard;

use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Http\FormRequest;

class IndexDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(PermissionResolver::class)->allows($this->user(), PermissionCodes::DASHBOARD_READ);
    }

    public function rules(): array
    {
        return [
            'renstra_id' => ['nullable', 'uuid'],
            'tahun' => ['nullable', 'integer', 'between:1,9999'],
            'periode_id' => ['nullable', 'uuid'],
            'sasaran_id' => ['nullable', 'uuid'],
            'unit_id' => ['nullable', 'uuid'],
        ];
    }
}
