<?php

namespace App\Actions\PenanggungJawab;

use App\Models\User;

class SearchPenanggungJawabUsers
{
    /** @return array<string,mixed> */
    public function handle(string $search): array
    {
        $rows = User::select(['id', 'nama', 'email', 'status'])->with('roles:id,nama,kode,aktif')
            ->where('status', 'aktif')->when($search !== '', function ($query) use ($search): void {
                $query->where(fn ($q) => $q->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'));
            })->orderBy('nama')->orderBy('id')->simplePaginate(20);

        return [
            'items' => $rows->getCollection()->map(fn (User $user) => [
                'id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'status' => $user->status,
                'roles' => $user->roles->where('aktif', true)->pluck('nama')->all(),
            ])->all(),
            'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages(),
        ];
    }
}
