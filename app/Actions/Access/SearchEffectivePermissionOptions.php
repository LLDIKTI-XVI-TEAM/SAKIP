<?php

namespace App\Actions\Access;

use App\Models\Unit;
use App\Models\User;

class SearchEffectivePermissionOptions
{
    /**
     * Pencarian terbatas untuk diagnosis, termasuk akun nonaktif dan tanpa peran.
     *
     * @param  array{q?: string|null, page?: int|numeric-string|null}  $input
     * @return array{items: list<array{id: string, nama: string, email: string, status: 'aktif'|'nonaktif', roles: list<string>}>, page: int, hasMore: bool}
     */
    public function users(array $input): array
    {
        $q = trim($input['q'] ?? '');
        $pattern = '%'.addcslashes($q, '\\%_').'%';
        $page = User::query()->select(['id', 'nama', 'email', 'status'])
            ->with('roles:id,nama,kode,aktif')
            ->when($q !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('nama', 'ilike', $pattern)->orWhere('email', 'ilike', $pattern)))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20, ['*'], 'page', (int) ($input['page'] ?? 1));

        return ['items' => $page->getCollection()->map(fn (User $user) => [
            ...$user->only(['id', 'nama', 'email', 'status']),
            'roles' => $user->roles->map(fn ($role) => $role->nama.($role->aktif ? '' : ' (nonaktif)'))->all(),
        ])->all(), 'page' => $page->currentPage(), 'hasMore' => $page->hasMorePages()];
    }

    /**
     * Unit nonaktif tetap tersedia untuk diagnosis; model hanya memuat id/nama/status.
     * Serialisasi model menjadi JSON dilakukan oleh response controller.
     *
     * @param  array{q?: string|null, page?: int|numeric-string|null}  $input
     * @return array{items: list<Unit>, page: int, hasMore: bool}
     */
    public function units(array $input): array
    {
        $q = trim($input['q'] ?? '');
        $pattern = '%'.addcslashes($q, '\\%_').'%';
        $page = Unit::query()->select(['id', 'nama', 'status'])
            ->when($q !== '', fn ($query) => $query->where('nama', 'ilike', $pattern))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20, ['*'], 'page', (int) ($input['page'] ?? 1));

        return ['items' => $page->items(), 'page' => $page->currentPage(), 'hasMore' => $page->hasMorePages()];
    }
}
