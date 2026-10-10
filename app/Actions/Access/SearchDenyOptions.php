<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;

class SearchDenyOptions
{
    /**
     * Calon pengguna untuk deny, termasuk akun nonaktif agar deny dapat dipasang sebelum aktivasi.
     *
     * @return array{items: list<array<string, mixed>>, page: int, hasMore: bool}
     */
    public function users(string $search): array
    {
        $rows = User::select(['id', 'nama', 'email', 'status'])
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20);

        return ['items' => $rows->getCollection()->map(fn (User $user) => $user->only(['id', 'nama', 'email', 'status']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()];
    }

    /** @return array{items: list<array<string, mixed>>, page: int, hasMore: bool} */
    public function units(string $search): array
    {
        $rows = Unit::select(['id', 'nama', 'status'])->when($search !== '', fn ($query) => $query->where('nama', 'ilike', '%'.$search.'%'))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20);

        return ['items' => $rows->getCollection()->map(fn (Unit $unit) => $unit->only(['id', 'nama', 'status']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()];
    }

    /**
     * Hanya permission katalog resmi yang aktif; permission legacy tidak ditawarkan sebagai target deny.
     *
     * @return array{items: list<array<string, mixed>>, page: int, hasMore: bool}
     */
    public function permissions(string $search): array
    {
        $rows = Permission::select(['id', 'kode', 'keterangan', 'butuh_scope'])->whereIn('kode', PermissionCatalog::codes())->where('aktif', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('kode', 'ilike', '%'.$search.'%')->orWhere('keterangan', 'ilike', '%'.$search.'%')))
            ->orderBy('kode')->orderBy('id')->simplePaginate(20);

        return ['items' => $rows->getCollection()->map(fn (Permission $permission) => $permission->only(['id', 'kode', 'keterangan', 'butuh_scope']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()];
    }
}
