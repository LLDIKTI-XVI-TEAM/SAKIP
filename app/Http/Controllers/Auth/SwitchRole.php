<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Switch role instan untuk kebutuhan development/testing alur kerja.
 * Hanya tersedia di lingkungan local.
 */
class SwitchRole extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
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
}
