<?php

namespace App\Http\Controllers\Perencanaan;

use App\Actions\Perencanaan\IndexSasaranIndikator as IndexSasaranIndikatorAction;
use App\Http\Controllers\Controller;
use App\Models\IndikatorKinerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexSasaranIndikator extends Controller
{
    public function __invoke(Request $request, IndexSasaranIndikatorAction $action): Response
    {
        Gate::authorize('viewAny', IndikatorKinerja::class);

        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('Perencanaan/SasaranIndikator/Index', $action->handle(
            $actor,
            $request->query('renstra_id'),
            $request->has('renstra_id'),
        ));
    }
}
