<?php

namespace App\Actions\Access;

use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentWarnings;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Pagination\LengthAwarePaginator;

class GetRoleAssignmentPage
{
    public function __construct(private RoleAssignmentWarnings $warnings) {}

    /**
     * Susun halaman bagi aktor yang sudah diotorisasi; query PJ dibatasi pengguna pada halaman ini.
     *
     * @return array{
     *     users: LengthAwarePaginator<int, array{id: string, nama: string, email: string, status: 'aktif'|'nonaktif', current_role: array{id: string, kode: string, nama: string, aktif: bool}|null, assignment: mixed, has_active_pj: bool}>,
     *     roles: list<array{id: string, kode: string, nama: string}>, filters: array{q: string}, can: array{assignRole: true}
     * }
     */
    public function handle(string $search, int $page): array
    {
        $users = User::select(['id', 'nama', 'email', 'status'])
            ->with('roles:id,kode,nama,aktif')
            ->when($search !== '', fn ($builder) => $builder->where(fn ($filter) => $filter
                ->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->orderBy('nama')->orderBy('id')->paginate(20, page: $page)->appends(['q' => $search]);
        $activePj = $this->warnings->forUsers($users->getCollection()->pluck('id')->all());
        $users->through(function (User $user) use ($activePj): array {
            $role = $user->roles->first();

            return [
                'id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'status' => $user->status,
                'current_role' => $role ? $role->only(['id', 'kode', 'nama', 'aktif']) : null,
                'assignment' => $role ? $role->getRelation('pivot')->only(['id', 'role_id', 'audit_id']) : null,
                'has_active_pj' => $activePj[$user->id],
            ];
        });
        $catalog = Role::whereIn('kode', RoleCatalog::codes())->where('aktif', true)->get(['id', 'kode', 'nama'])->keyBy('kode');
        $roles = [];
        foreach (RoleCatalog::codes() as $kode) {
            if ($role = $catalog->get($kode)) {
                $roles[] = $role->only(['id', 'kode', 'nama']);
            }
        }

        return ['users' => $users, 'roles' => $roles, 'filters' => ['q' => $search], 'can' => ['assignRole' => true]];
    }
}
