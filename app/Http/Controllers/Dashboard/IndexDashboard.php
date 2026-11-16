<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\GetDashboard;
use App\Http\Requests\Dashboard\IndexDashboardRequest;
use Inertia\Inertia;
use Inertia\Response;

class IndexDashboard
{
    public function __invoke(IndexDashboardRequest $request, GetDashboard $dashboard): Response
    {
        return Inertia::render('Dashboard/Index', $dashboard->handle($request->user(), $request->validated()));
    }
}
