<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\GetDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexDashboard
{
    public function __invoke(Request $request, GetDashboard $dashboard): Response
    {
        return Inertia::render('Dashboard/Index', $dashboard->handle($request->user()));
    }
}
