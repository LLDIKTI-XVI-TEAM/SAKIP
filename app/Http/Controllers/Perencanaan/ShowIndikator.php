<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\ShowIndikator as ShowIndikatorAction;
use App\Http\Controllers\Controller;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowIndikator extends Controller
{
    public function __invoke(Request $request, IndikatorKinerja $indikator, ShowIndikatorAction $action): Response
    {
        Gate::authorize('view', $indikator);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Perencanaan/Indikator/Show', $action->handle($actor, $indikator));
    }
}
