<?php

namespace App\Http\Controllers\Renstra;

use App\Actions\Renstra\EditRenstraForm;
use App\Http\Controllers\Controller;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra, EditRenstraForm $action): Response
    {
        Gate::authorize('view', $renstra);
        Gate::authorize('update', $renstra);

        if (! in_array($renstra->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true)) {
            abort(403, 'Renstra nonaktif atau diarsipkan hanya dapat dibaca.');
        }

        return Inertia::render('Renstra/Edit', $action->handle($request->user(), $renstra));
    }
}
