<?php

namespace App\Http\Controllers\PenanggungJawab;

use App\Actions\PenanggungJawab\AssignPenanggungJawab;
use App\Actions\PenanggungJawab\ChangePenanggungJawab;
use App\Actions\PenanggungJawab\MonitorPenanggungJawab;
use App\Actions\PenanggungJawab\ReadPenanggungJawab;
use App\Actions\PenanggungJawab\ReadWorkReadiness;
use App\Actions\PenanggungJawab\SearchPenanggungJawabUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\PenanggungJawab\AssignmentRequest;
use App\Http\Requests\PenanggungJawab\QueryRequest;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PenanggungJawabController extends Controller
{
    public function index(QueryRequest $request, MonitorPenanggungJawab $action): Response
    {
        return Inertia::render('PenanggungJawab/Index', $action->handle($request->validated()));
    }

    public function show(QueryRequest $request, IndikatorKinerja $indikator, ReadPenanggungJawab $action): Response
    {
        return Inertia::render('PenanggungJawab/Show', $action->handle($indikator, $request->validated()) + ['saved_assignment_id' => $request->session()->get('pj_saved_assignment_id')]);
    }

    public function users(QueryRequest $request, SearchPenanggungJawabUsers $action): JsonResponse
    {
        return response()->json($action->handle(trim($request->validated('q', '') ?? '')));
    }

    public function readiness(QueryRequest $request, IndikatorKinerja $indikator, ReadWorkReadiness $action): JsonResponse
    {
        return response()->json($action->handle($indikator, $request->validated('user_id')));
    }

    public function store(AssignmentRequest $request, IndikatorKinerja $indikator, AssignPenanggungJawab $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->saved($indikator, $action->handle($actor, $indikator, $request->validated()));
    }

    public function change(AssignmentRequest $request, IndikatorKinerja $indikator, ChangePenanggungJawab $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        return $this->saved($indikator, $action->handle($actor, $indikator, $request->validated()));
    }

    /** @param array<string,mixed> $result */
    private function saved(IndikatorKinerja $indicator, array $result): RedirectResponse
    {
        return redirect()->route('penanggung-jawab.show', ['indikator' => $indicator->id])
            ->with('success', 'Penugasan penanggung jawab tersimpan sesuai tanggal mulai berlaku.')
            ->with('warning', $result['warning'])
            ->with('pj_saved_assignment_id', $result['assignment']->id);
    }
}
