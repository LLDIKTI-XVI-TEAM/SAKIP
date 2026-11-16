<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CreateRegulasi extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('create', Regulasi::class);

        return Inertia::render('Regulasi/Create');
    }
}
