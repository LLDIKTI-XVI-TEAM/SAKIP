<?php

namespace App\Http\Controllers\Audit;

use App\Actions\Audit\CariAuditLog;
use App\Actions\Audit\TampilkanAuditLog;
use App\Http\Requests\Audit\AuditLogRequest;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController
{
    public function index(AuditLogRequest $request, CariAuditLog $action): Response
    {
        return Inertia::render('Audit/Index', $action->handle($request->validated()));
    }

    public function show(AuditLogRequest $request, string $id, TampilkanAuditLog $action): Response
    {
        return Inertia::render('Audit/Show', $action->handle($id));
    }
}
