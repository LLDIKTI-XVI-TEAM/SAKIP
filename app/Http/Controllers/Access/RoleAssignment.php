<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\AssignRole;
use App\Actions\Access\GetRoleAssignmentPage;
use App\Http\Requests\Access\AssignRoleRequest;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Inertia\Support\SessionKey;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class RoleAssignment
{
    public function index(Request $request, PermissionResolver $permissions, GetRoleAssignmentPage $getPage, RoleAssignmentReceipt $receipts): HttpResponse
    {
        $actor = $request->user()->fresh();
        abort_unless($permissions->allows($actor, 'pengguna:read') && $permissions->allows($actor, 'akses:update'), 403);

        return $this->indexResponse($request, $actor, $getPage, $receipts);
    }

    private function indexResponse(Request $request, User $actor, GetRoleAssignmentPage $getPage, RoleAssignmentReceipt $receipts, bool $confirmationUnavailable = false): HttpResponse
    {
        $query = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $props = $getPage->handle(trim($query['q'] ?? ''), (int) ($query['page'] ?? 1));
        $reference = $this->reference($request);
        $outcome = $reference === null ? null : $receipts->consume($actor->id, $request->session()->getId(), $reference);
        $confirmationUnavailable = $confirmationUnavailable || ($request->query->has('receipt') && $outcome === null);

        return $this->renderOutcome($request, Inertia::render('Access/RoleAssignmentIndex', [
            ...$props,
            'confirmationUnavailable' => $confirmationUnavailable,
        ]), $outcome);
    }

    public function store(AssignRoleRequest $request, string $user, AssignRole $assign, RoleAssignmentReceipt $receipts): RedirectResponse
    {
        $outcome = $assign->handle($request->user(), $user, $request->validated('role_id'), $request->validated('alasan'), $request->validated('expected_assignment'));
        $reference = $receipts->issue($request->user()->id, $request->session()->getId(), $outcome);
        Inertia::clearHistory();

        return redirect()->route('role-assignment.result', $reference === null ? [] : ['receipt' => $reference], 303);
    }

    public function result(Request $request, PermissionResolver $permissions, GetRoleAssignmentPage $getPage, RoleAssignmentReceipt $receipts): HttpResponse
    {
        $actor = $request->user()->fresh();
        $reference = $this->reference($request);
        $canReturn = $permissions->allows($actor, 'pengguna:read') && $permissions->allows($actor, 'akses:update');
        if ($canReturn) {
            if ($reference !== null) {
                return redirect()->route('role-assignment.index', ['receipt' => $reference]);
            }

            // Component yang sama menjaga dialog/draft POST ketika delivery tidak dapat dikonfirmasi.
            return $this->indexResponse($request, $actor, $getPage, $receipts, true);
        }
        $outcome = $reference === null ? null : $receipts->consume($actor->id, $request->session()->getId(), $reference);

        // Receipt tidak membawa identitas target ketika aktor kehilangan hak baca setelah self-change.
        return $this->renderOutcome($request, Inertia::render('Access/RoleAssignmentResult', [
            'canReturn' => $canReturn,
        ]), $outcome);
    }

    /**
     * Evaluasi props/response di dalam cleanup agar exception sebelum pullFlashed tidak membocorkan sukses.
     *
     * @param  array{receipt_id:string,status:'assigned'|'changed'|'unchanged',has_active_pj:bool}|null  $outcome
     */
    private function renderOutcome(Request $request, Response $page, ?array $outcome): HttpResponse
    {
        $leaf = SessionKey::FLASH_DATA.'.roleAssignmentOutcome';
        $request->session()->forget($leaf);
        try {
            $reference = $this->reference($request);
            if ($reference !== null && $outcome !== null && count($outcome) === 3
                && ($outcome['receipt_id'] ?? null) === $reference
                && in_array($outcome['status'] ?? null, ['assigned', 'changed', 'unchanged'], true)
                && is_bool($outcome['has_active_pj'] ?? null)) {
                Inertia::flash('roleAssignmentOutcome', $outcome);
            }

            return $page->toResponse($request);
        } finally {
            $request->session()->forget($leaf);
        }
    }

    /** Reference hanya korelasi hasil; field duplikat/array tidak boleh memilih operasi secara ambigu. */
    private function reference(Request $request): ?string
    {
        $reference = $request->query('receipt');
        $references = array_filter(explode('&', (string) $request->server('QUERY_STRING')), fn (string $part): bool => urldecode(explode('=', $part, 2)[0]) === 'receipt');

        return is_string($reference) && Str::isUuid($reference) && count($references) === 1 ? $reference : null;
    }
}
