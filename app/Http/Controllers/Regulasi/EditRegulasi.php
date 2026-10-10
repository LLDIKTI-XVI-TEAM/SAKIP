<?php

namespace App\Http\Controllers\Regulasi;

use App\Actions\Regulasi\PresentRegulasi;
use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditRegulasi extends Controller
{
    public function __invoke(Regulasi $regulasi, PresentRegulasi $action): Response
    {
        Gate::authorize('update', $regulasi);

        return Inertia::render('Regulasi/Edit', $action->handle($regulasi, editor: true));
    }
}
