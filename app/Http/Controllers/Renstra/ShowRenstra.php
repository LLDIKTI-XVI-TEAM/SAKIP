<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\ShowRenstra as ShowRenstraAction;
use App\Http\Controllers\Controller;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra, ShowRenstraAction $action): Response
    {
        Gate::authorize('view', $renstra);

        return Inertia::render('Renstra/Show', $action->handle($request->user(), $renstra));
    }
}
