<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\IndexGrant as IndexGrantAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexGrant extends Controller
{
    public function __invoke(Request $request, IndexGrantAction $action): Response
    {
        Gate::authorize('delegasi:update');

        return Inertia::render('Akses/GrantIndex', $action->handle($request->user(), trim($request->string('search')->toString()), $request->query('unit_id')));
    }
}
