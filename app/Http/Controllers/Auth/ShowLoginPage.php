<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ShowLoginPage extends Controller
{
    public function __invoke(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $demoUsers = app()->environment('local') ? User::with('roles', 'unitKerja')->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->roles->first()?->name ?? 'pegawai',
                'unit' => $u->unitKerja?->singkatan ?? 'LLDIKTI',
            ];
        }) : [];

        return Inertia::render('Auth/Login', [
            'demoUsers' => $demoUsers,
        ]);
    }
}
