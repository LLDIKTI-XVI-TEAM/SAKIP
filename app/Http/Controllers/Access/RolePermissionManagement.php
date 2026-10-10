<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\GetRolePermissionPage;
use App\Policies\RolePermissionPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionManagement
{
    public function index(Request $request, RolePermissionPolicy $policy, GetRolePermissionPage $getPage): Response
    {
        abort_unless($policy->decide($request->user()->fresh())['allowed'], 403);
        $input = $request->validate([
            'role' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return Inertia::render('Access/RolePermissionIndex', $getPage->handle($input['role'] ?? null, trim($input['q'] ?? ''), (int) ($input['page'] ?? 1)));
    }
}
