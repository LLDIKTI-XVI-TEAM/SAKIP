<?php

namespace App\Http\Controllers\Access;

use App\Models\Permission;
use App\Models\Role;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionManagement
{
    public function index(Request $request, RolePermissionPolicy $policy): Response
    {
        abort_unless($policy->decide($request->user()->fresh())['allowed'], 403);
        $input = $request->validate([
            'role' => ['nullable', 'uuid'], 'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $catalog = Role::whereIn('kode', RoleCatalog::codes())->get(['id', 'kode', 'nama', 'aktif'])->keyBy('kode');
        $roles = [];
        foreach (RoleCatalog::codes() as $code) {
            if ($role = $catalog->get($code)) {
                $roles[] = $role->only(['id', 'kode', 'nama', 'aktif']);
            }
        }
        $filters = ['role' => $input['role'] ?? null, 'q' => trim($input['q'] ?? '')];
        $props = ['roles' => $roles, 'selectedRole' => null, 'permissions' => [],
            'pagination' => ['page' => 1, 'prev_page_url' => null, 'next_page_url' => null],
            'filters' => $filters, 'can' => ['viewRolePermissions' => true]];
        if ($filters['role'] !== null) {
            $role = $catalog->firstWhere('id', $filters['role']);
            if (! $role) {
                throw ValidationException::withMessages(['role' => 'Pilih peran resmi.']);
            }
            // Baca membership aktual; metadata nonaktif/legacy bukan klaim izin efektif pengguna.
            $page = Permission::whereIn('id', function ($query) use ($role) {
                $query->select('permission_id')->from('role_permissions')->where('role_id', $role->id);
            })->when($filters['q'] !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('kode', 'ilike', '%'.$filters['q'].'%')->orWhere('keterangan', 'ilike', '%'.$filters['q'].'%')))
                ->orderBy('kode')->simplePaginate(20, ['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'], 'page', (int) ($input['page'] ?? 1))
                ->withPath(route('role-permission.index'))->appends($filters);
            $props['selectedRole'] = $role->only(['id', 'kode', 'nama', 'aktif']);
            $props['permissions'] = $page->getCollection()->map(fn (Permission $permission) => $permission->only(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'])
                + ['in_catalog' => in_array($permission->kode, PermissionCatalog::codes(), true)])->all();
            $props['pagination'] = ['page' => $page->currentPage(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()];
        }

        return Inertia::render('Access/RolePermissionIndex', $props);
    }
}
