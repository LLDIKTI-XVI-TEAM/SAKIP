<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Access\ShowGrantUnit;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowGrant extends Controller
{
    public function __invoke(string $user, string $unit, ShowGrantUnit $action): Response
    {
        Gate::authorize('delegasi:update');

        return Inertia::render('Akses/GrantDetail', $action->handle($user, $unit));
    }
}
