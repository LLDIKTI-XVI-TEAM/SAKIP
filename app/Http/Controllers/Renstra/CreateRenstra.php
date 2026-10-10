<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\CreateRenstraForm;
use App\Http\Controllers\Controller;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CreateRenstra extends Controller
{
    public function __invoke(Request $request, CreateRenstraForm $action): Response
    {
        Gate::authorize('create', Renstra::class);

        return Inertia::render('Renstra/Create', $action->handle($request->user()));
    }
}
