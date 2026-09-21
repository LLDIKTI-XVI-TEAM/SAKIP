<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\AssignRole;
use App\Http\Requests\Access\AssignRoleRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleAssignment
{
    public function index(Request $request, PermissionResolver $permissions): Response
    {
        $actor = $request->user()->fresh();
        abort_unless($permissions->allows($actor, 'pengguna:read') && $permissions->allows($actor, 'akses:update'), 403);
        $query = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($query['q'] ?? '');
        $users = User::select(['id', 'nama', 'email', 'is_active'])
            ->with('roles:id,kode,nama,aktif')
            ->when($search !== '', fn ($builder) => $builder->where(fn ($filter) => $filter
                ->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->orderBy('nama')->orderBy('id')->paginate(20)->withQueryString()
            ->through(function (User $user): array {
                $role = $user->roles->first();

                return [
                    'id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'is_active' => $user->is_active,
                    'current_role' => $role ? $role->only(['id', 'kode', 'nama', 'aktif']) : null,
                    'assignment' => $role ? $role->getRelation('pivot')->only(['id', 'role_id', 'audit_id']) : null,
                ];
            });
        $catalog = Role::whereIn('kode', RoleCatalog::codes())->where('aktif', true)->get(['id', 'kode', 'nama'])->keyBy('kode');
        $roles = [];
        foreach (RoleCatalog::codes() as $kode) {
            if ($role = $catalog->get($kode)) {
                $roles[] = $role->only(['id', 'kode', 'nama']);
            }
        }

        return Inertia::render('Access/RoleAssignmentIndex', [
            'users' => $users, 'roles' => $roles, 'filters' => ['q' => $search], 'can' => ['assignRole' => true],
        ]);
    }

    public function store(AssignRoleRequest $request, string $user, AssignRole $assign): RedirectResponse
    {
        $status = $assign->handle($request->user(), $user, $request->validated('role_id'), $request->validated('alasan'), $request->validated('expected_assignment'));
        Inertia::clearHistory();

        return redirect()->route('role-assignment.result', status: 303)
            ->with('roleAssignmentResult', ['actor_id' => $request->user()->id, 'status' => $status]);
    }

    public function result(Request $request, PermissionResolver $permissions): Response|RedirectResponse
    {
        $actor = $request->user()->fresh();
        $receipt = $request->session()->get('roleAssignmentResult');
        $status = is_array($receipt) && ($receipt['actor_id'] ?? null) === $actor->id
            && in_array($receipt['status'] ?? null, ['assigned', 'changed', 'unchanged'], true) ? $receipt['status'] : null;
        $canReturn = $permissions->allows($actor, 'pengguna:read') && $permissions->allows($actor, 'akses:update');
        if ($status !== null && $canReturn) {
            // Flash khusus Inertia tidak diputar ulang sebagai sukses baru dari browser history.
            Inertia::flash('roleAssignmentStatus', $status);

            return redirect()->route('role-assignment.index');
        }

        // Receipt tidak membawa identitas target ketika aktor kehilangan hak baca setelah self-change.
        return Inertia::render('Access/RoleAssignmentResult', [
            'status' => $status,
            'canReturn' => $canReturn,
        ]);
    }
}
