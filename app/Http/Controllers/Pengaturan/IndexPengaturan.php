<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Services\Authorization\PermissionResolver;
use App\Services\PengaturanService;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengaturan extends Controller
{
    public function __construct(
        private readonly PengaturanService $pengaturanService,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user()?->fresh();
        abort_unless(
            $user && $this->permissionResolver->allows($user, PermissionCodes::PENGATURAN_UPDATE),
            403,
            'Akses ke pengaturan sistem ditolak.'
        );

        $data = $this->pengaturanService->allGrouped();

        return Inertia::render('Pengaturan/Index', [
            'grouped' => $data['grouped'],
            'values' => $data['values'],
        ]);
    }
}
