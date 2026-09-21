<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\CreateDeny;
use App\Actions\Access\RevokeDeny;
use App\Http\Requests\Access\CreateDenyRequest;
use App\Http\Requests\Access\RevokeDenyRequest;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DenyManagement
{
    public function __construct(private PermissionResolver $permissions) {}

    private function search(Request $request): string
    {
        abort_unless($this->permissions->allows($request->user()->fresh(), 'akses:update'), 403);
        $query = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        return trim($query['q'] ?? '');
    }

    public function index(Request $request): Response
    {
        $search = $this->search($request);
        $rows = UserPermissionDeny::select(['id', 'user_id', 'permission_id', 'unit_id', 'alasan', 'ditetapkan_oleh', 'created_at'])
            ->with(['user:id,nama,email,is_active', 'permission:id,kode,keterangan,butuh_scope,aktif', 'unit:id,nama,status', 'penetap:id,nama'])
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where(fn ($filter) => $filter->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%'))))
            ->orderByDesc('created_at')->orderByDesc('id')->simplePaginate(20)->withQueryString();

        return Inertia::render('Access/DenyIndex', [
            'denies' => $rows->getCollection()->map(fn (UserPermissionDeny $deny) => [
                'id' => $deny->id, 'user' => $deny->user->only(['id', 'nama', 'email', 'is_active']),
                'permission' => $deny->permission->only(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif']),
                'unit' => $deny->unit?->only(['id', 'nama', 'status']), 'alasan' => $deny->alasan,
                'ditetapkan_oleh' => $deny->penetap->only(['id', 'nama']), 'created_at' => $deny->created_at->toISOString(),
            ])->all(),
            'pagination' => ['current_page' => $rows->currentPage(), 'prev_page_url' => $rows->previousPageUrl(), 'next_page_url' => $rows->nextPageUrl()],
            'filters' => ['q' => $search], 'can' => ['manageDeny' => true],
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $search = $this->search($request);
        $rows = User::select(['id', 'nama', 'email', 'is_active'])
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('nama', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20);

        return response()->json(['items' => $rows->getCollection()->map(fn (User $user) => $user->only(['id', 'nama', 'email', 'is_active']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()]);
    }

    public function units(Request $request): JsonResponse
    {
        $search = $this->search($request);
        $rows = Unit::select(['id', 'nama', 'status'])->when($search !== '', fn ($query) => $query->where('nama', 'ilike', '%'.$search.'%'))
            ->orderBy('nama')->orderBy('id')->simplePaginate(20);

        return response()->json(['items' => $rows->getCollection()->map(fn (Unit $unit) => $unit->only(['id', 'nama', 'status']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()]);
    }

    public function permissions(Request $request): JsonResponse
    {
        $search = $this->search($request);
        $rows = Permission::select(['id', 'kode', 'keterangan', 'butuh_scope'])->whereIn('kode', PermissionCatalog::codes())->where('aktif', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($filter) => $filter->where('kode', 'ilike', '%'.$search.'%')->orWhere('keterangan', 'ilike', '%'.$search.'%')))
            ->orderBy('kode')->orderBy('id')->simplePaginate(20);

        return response()->json(['items' => $rows->getCollection()->map(fn (Permission $permission) => $permission->only(['id', 'kode', 'keterangan', 'butuh_scope']))->all(), 'page' => $rows->currentPage(), 'hasMore' => $rows->hasMorePages()]);
    }

    public function store(CreateDenyRequest $request, CreateDeny $create): RedirectResponse
    {
        $create->handle($request->user(), $request->validated('user_id'), $request->validated('permission_id'), $request->validated('unit_id'), $request->validated('alasan'));

        return $this->receipt($request, 'created');
    }

    public function revoke(RevokeDenyRequest $request, string $deny, RevokeDeny $revoke): RedirectResponse
    {
        $revoke->handle($request->user(), $deny, $request->validated('alasan'));

        return $this->receipt($request, 'revoked');
    }

    private function receipt(Request $request, string $status): RedirectResponse
    {
        Inertia::clearHistory();

        return redirect()->route('deny.result', status: 303)->with('denyResult', ['actor_id' => $request->user()->id, 'status' => $status]);
    }

    public function result(Request $request): Response|RedirectResponse
    {
        $actor = $request->user()->fresh();
        $receipt = $request->session()->get('denyResult');
        $status = is_array($receipt) && ($receipt['actor_id'] ?? null) === $actor->id
            && in_array($receipt['status'] ?? null, ['created', 'revoked'], true) ? $receipt['status'] : null;
        $canReturn = $this->permissions->allows($actor, 'akses:update');
        if ($status !== null && $canReturn) {
            Inertia::flash('denyStatus', $status);

            return redirect()->route('deny.index');
        }

        return Inertia::render('Access/DenyResult', ['status' => $status, 'canReturn' => $canReturn]);
    }
}
