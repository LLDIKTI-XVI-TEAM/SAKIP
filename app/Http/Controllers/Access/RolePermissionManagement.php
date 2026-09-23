<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\ChangeRolePermission;
use App\Http\Requests\Access\ChangeRolePermissionRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Policies\RolePermissionPolicy;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\RoleCatalog;
use App\Services\Authorization\RolePermissionReceipt;
use App\Services\Authorization\RolePermissionState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RolePermissionManagement
{
    public function index(Request $request, RolePermissionPolicy $policy, RolePermissionState $state): Response
    {
        $actor = $request->user()->fresh();
        abort_unless($policy->decide($actor)['allowed'], 403);
        $filters = $request->validate([
            'role' => ['nullable', 'uuid'], 'view' => ['nullable', 'in:attached,available'],
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
        ]);
        Inertia::flash('rolePermissionOutcome', null);

        return Inertia::render('Access/RolePermissionIndex', $this->indexProps($actor, $filters, $state) + ['receiptId' => null]);
    }

    public function store(ChangeRolePermissionRequest $request, string $role, ChangeRolePermission $change, RolePermissionReceipt $receipts): RedirectResponse
    {
        $status = $change->handle($request->user(), $role, $request->validated('permission_id'),
            $request->validated('operation'), $request->validated('alasan'), $request->validated('expected_state'));
        $reference = $receipts->issue($request->user()->id, $request->session()->getId(), $status);
        Inertia::clearHistory();

        return redirect()->route('role-permission.result', $reference === null ? [] : ['receipt' => $reference], status: 303);
    }

    public function result(Request $request, RolePermissionPolicy $policy, RolePermissionReceipt $receipts, RolePermissionState $state): Response
    {
        $actor = $request->user()->fresh();
        $reference = $request->query('receipt');
        $reference = is_string($reference) && Str::isUuid($reference) ? $reference : null;
        $receipt = $reference === null ? null : $receipts->consume($actor->id, $request->session()->getId(), $reference);
        $canReturn = $policy->decide($actor)['allowed'];
        // Flash dipull dalam respons ini, bukan dititipkan melalui redirect antartab.
        Inertia::flash('rolePermissionOutcome', $receipt);
        if ($receipt !== null && $canReturn) {
            return Inertia::render('Access/RolePermissionIndex', $this->indexProps($actor, [], $state) + ['receiptId' => $reference]);
        }

        return Inertia::render('Access/RolePermissionResult', ['receiptId' => $reference, 'canReturn' => $canReturn]);
    }

    /**
     * Projection dibatasi satu role; daftar attached dan token dibaca dalam lock yang sama.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function indexProps(User $actor, array $input, RolePermissionState $state): array
    {
        $catalog = Role::whereIn('kode', RoleCatalog::codes())->where('aktif', true)->get(['id', 'kode', 'nama'])->keyBy('kode');
        $roles = [];
        foreach (RoleCatalog::codes() as $code) {
            if ($role = $catalog->get($code)) {
                $roles[] = $role->only(['id', 'kode', 'nama']);
            }
        }
        $filters = ['role' => $input['role'] ?? null, 'view' => $input['view'] ?? 'attached', 'q' => trim($input['q'] ?? '')];
        $props = ['roles' => $roles, 'selectedRole' => null, 'expectedState' => null, 'permissions' => [],
            'pagination' => ['page' => 1, 'prev_page_url' => null, 'next_page_url' => null],
            'filters' => $filters, 'can' => ['manageRolePermissions' => true], 'affectsActorRole' => false];
        if ($filters['role'] === null) {
            return $props;
        }

        return DB::transaction(function () use ($actor, $input, $state, $filters, $props) {
            $role = Role::whereKey($filters['role'])->sharedLock()->first();
            if (! $role || ! $role->aktif || ! RoleCatalog::contains($role->kode)) {
                throw ValidationException::withMessages(['role' => 'Pilih peran resmi yang aktif.']);
            }
            $attached = DB::table('role_permissions')->where('role_id', $role->id)->pluck('permission_id')->all();
            Permission::whereIn('id', $attached)->orderBy('id')->sharedLock()->get(['id']);
            $snapshot = $state->capture($role);
            $query = Permission::select(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif']);
            if ($filters['view'] === 'attached') {
                $query->whereIn('id', $attached);
            } else {
                $query->whereNotIn('id', $attached)->whereIn('kode', PermissionCatalog::codes())->where('aktif', true)->where('butuh_scope', 'global');
            }
            $page = $query->when($filters['q'] !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('kode', 'ilike', '%'.$filters['q'].'%')->orWhere('keterangan', 'ilike', '%'.$filters['q'].'%')))
                ->orderBy('kode')->orderBy('id')->simplePaginate(20, ['*'], 'page', (int) ($input['page'] ?? 1))
                ->withPath(route('role-permission.index'))->appends($filters);
            $rows = $page->getCollection()->map(function (Permission $permission) use ($filters): array {
                $reason = match (true) {
                    ! in_array($permission->kode, PermissionCatalog::codes(), true) => 'unknown',
                    ! $permission->aktif => 'inactive',
                    $permission->butuh_scope !== 'global' => 'scoped',
                    default => null,
                };

                return $permission->only(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'])
                    + ['attached' => $filters['view'] === 'attached', 'editable' => $reason === null, 'non_editable_reason' => $reason];
            })->all();

            return array_replace($props, [
                'selectedRole' => $role->only(['id', 'kode', 'nama']), 'expectedState' => $snapshot['token'], 'permissions' => $rows,
                'pagination' => ['page' => $page->currentPage(), 'prev_page_url' => $page->previousPageUrl(), 'next_page_url' => $page->nextPageUrl()],
                'affectsActorRole' => DB::table('user_roles')->where('user_id', $actor->id)->where('role_id', $role->id)->exists(),
            ]);
        });
    }
}
