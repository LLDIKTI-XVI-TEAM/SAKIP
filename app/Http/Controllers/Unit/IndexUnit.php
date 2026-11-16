<?php

namespace App\Http\Controllers\Unit;

use App\Actions\Unit\IndexUnit as IndexUnitAction;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexUnit extends Controller
{
    public function __invoke(Request $request, IndexUnitAction $action): Response
    {
        Gate::authorize('viewAny', Unit::class);

        return Inertia::render('Unit/Index', $action->handle($request->user()));
    }
}
