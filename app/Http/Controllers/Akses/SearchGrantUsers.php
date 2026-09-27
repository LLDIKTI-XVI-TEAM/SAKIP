<?php

namespace App\Http\Controllers\Akses;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchGrantUsers extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('delegasi:update');

        $search = trim($request->string('q')->toString());

        $rows = User::select(['id', 'nama', 'email', 'is_active'])
            ->with('roles:id,nama,kode,aktif')
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($filter) use ($search) {
                    $filter->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->orderBy('id')
            ->simplePaginate(20);

        return response()->json([
            'items' => $rows->getCollection()->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'nama' => $user->nama,
                    'name' => $user->nama,
                    'email' => $user->email,
                    'is_active' => $user->is_active,
                    'roles' => $user->roles->pluck('nama')->all(),
                ];
            })->all(),
            'page' => $rows->currentPage(),
            'hasMore' => $rows->hasMorePages(),
        ]);
    }
}
