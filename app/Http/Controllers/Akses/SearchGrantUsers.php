<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\SearchGrantUsers as SearchGrantUsersAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchGrantUsers extends Controller
{
    public function __invoke(Request $request, SearchGrantUsersAction $action): JsonResponse
    {
        Gate::authorize('delegasi:update');

        return response()->json($action->handle(trim($request->string('q')->toString())));
    }
}
