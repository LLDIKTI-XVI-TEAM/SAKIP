<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DevAuthController extends Controller
{
    /**
     * Switch role instan untuk kebutuhan development/testing alur kerja
     */
    public function switchRole(Request $request, int $id): RedirectResponse
    {
        if (!app()->environment('local')) {
            abort(403, 'Aksi ini hanya tersedia di lingkungan development.');
        }

        $user = User::findOrFail($id);
        Auth::login($user);
        $request->session()->regenerate();

        $roleName = $user->roles->first()?->name ?? 'pegawai';

        return redirect()->back()->with('success', "Berhasil beralih ke: {$user->name} ({$roleName})");
    }

    /**
     * Logout
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('message', 'Anda telah keluar.');
    }
}
