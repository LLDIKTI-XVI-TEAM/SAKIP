<?php

namespace App\Actions\Access;

use App\Models\UserPermissionDeny;

class IndexDeny
{
    /**
     * Daftar deny eksplisit terbaru dengan relasi ringkas; pencarian hanya pada nama/email pengguna yang ditolak.
     *
     * @return array<string, mixed>
     */
    public function handle(string $search): array
    {
        $rows = UserPermissionDeny::select(['id', 'user_id', 'permission_id', 'unit_id', 'alasan', 'ditetapkan_oleh', 'created_at'])
            ->with(['user:id,nama,email,status', 'permission:id,kode,keterangan,butuh_scope,aktif', 'unit:id,nama,status', 'penetap:id,nama'])
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where(fn ($filter) => $filter->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'))))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20)->withQueryString();

        return [
            'denies' => $rows->getCollection()->map(fn (UserPermissionDeny $deny) => [
                'id' => $deny->id, 'user' => $deny->user->only(['id', 'nama', 'email', 'status']),
                'permission' => $deny->permission->only(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif']),
                'unit' => $deny->unit?->only(['id', 'nama', 'status']), 'alasan' => $deny->alasan,
                'ditetapkan_oleh' => $deny->penetap->only(['id', 'nama']), 'created_at' => $deny->created_at->toISOString(),
            ])->all(),
            'pagination' => ['current_page' => $rows->currentPage(), 'prev_page_url' => $rows->previousPageUrl(), 'next_page_url' => $rows->nextPageUrl()],
            'filters' => ['q' => $search], 'can' => ['manageDeny' => true],
        ];
    }
}
