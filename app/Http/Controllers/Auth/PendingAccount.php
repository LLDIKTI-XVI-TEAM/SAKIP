<?php

namespace App\Http\Controllers\Auth;

use App\Services\Authorization\RoleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingAccount
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user()->fresh();
        if ($user->status === 'aktif' && $user->roles()->whereIn('kode', RoleCatalog::codes())->where('roles.aktif', true)->exists()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/Pending', ['pendingReason' => $user->status === 'aktif' ? 'role' : 'activation']);
    }
}
