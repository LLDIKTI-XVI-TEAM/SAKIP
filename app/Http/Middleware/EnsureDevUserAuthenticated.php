<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDevUserAuthenticated
{
    /**
     * Pastikan selalu ada user aktif default (Superadmin) saat auth/login belum diimplementasikan,
     * sehingga seluruh route dan desain halaman dapat diakses langsung.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $defaultUser = User::role('superadmin')->first() ?? User::first();
            if ($defaultUser) {
                Auth::setUser($defaultUser);
                $request->setUserResolver(fn () => $defaultUser);
            }
        }

        return $next($request);
    }
}
