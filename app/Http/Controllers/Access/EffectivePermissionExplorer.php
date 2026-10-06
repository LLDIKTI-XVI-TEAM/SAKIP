<?php

namespace App\Http\Controllers\Access;

use App\Actions\Access\GetEffectivePermissionPage;
use App\Actions\Access\SearchEffectivePermissionOptions;
use App\Http\Requests\Access\EffectivePermissionRequest;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EffectivePermissionExplorer
{
    public function index(EffectivePermissionRequest $request, GetEffectivePermissionPage $action): Response
    {
        return Inertia::render('Access/EffectivePermissionIndex', $action->execute($request->validated()));
    }

    public function users(EffectivePermissionRequest $request, SearchEffectivePermissionOptions $action): JsonResponse
    {
        return response()->json($action->users($request->validated()));
    }

    public function units(EffectivePermissionRequest $request, SearchEffectivePermissionOptions $action): JsonResponse
    {
        return response()->json($action->units($request->validated()));
    }
}
