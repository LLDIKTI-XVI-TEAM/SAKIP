<?php

namespace App\Actions\Access;

use App\Models\User;

class SearchGrantUsers
{
    /**
     * Pencarian akun aktif sebagai calon penerima grant; akun nonaktif sengaja tidak ditawarkan.
     *
     * @return array{items: list<array<string, mixed>>, page: int, hasMore: bool}
     */
    public function handle(string $search): array
    {
        $rows = User::select(['id', 'nama', 'email', 'status'])
            ->with('roles:id,nama,kode,aktif')
            ->where('status', 'aktif')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($filter) use ($search) {
                    $filter->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->orderBy('id')
            ->simplePaginate(20);

        return [
            'items' => $rows->getCollection()->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'name' => $user->nama,
                    'email' => $user->email,
                    'status' => $user->status,
                    'roles' => $user->roles->pluck('nama')->all(),
                ];
            })->all(),
            'page' => $rows->currentPage(),
            'hasMore' => $rows->hasMorePages(),
        ];
    }
}
