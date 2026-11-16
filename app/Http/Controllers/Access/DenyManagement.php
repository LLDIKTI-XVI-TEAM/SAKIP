<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\CreateDeny;
use App\Actions\Access\IndexDeny;
use App\Actions\Access\RevokeDeny;
use App\Actions\Access\SearchDenyOptions;
use App\Http\Requests\Access\CreateDenyRequest;
use App\Http\Requests\Access\RevokeDenyRequest;
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

    public function index(Request $request, IndexDeny $action): Response
    {
        return Inertia::render('Access/DenyIndex', $action->handle($this->search($request)));
    }

    public function users(Request $request, SearchDenyOptions $options): JsonResponse
    {
        return response()->json($options->users($this->search($request)));
    }

    public function units(Request $request, SearchDenyOptions $options): JsonResponse
    {
        return response()->json($options->units($this->search($request)));
    }

    public function permissions(Request $request, SearchDenyOptions $options): JsonResponse
    {
        return response()->json($options->permissions($this->search($request)));
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
