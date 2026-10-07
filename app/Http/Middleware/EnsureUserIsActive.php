<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Baca status saat request, termasuk session yang dibentuk sebelum penonaktifan.
        if ($request->user()?->fresh()?->status !== 'aktif') {
            // Respons tanpa navigasi menjaga draft form saat akun dinonaktifkan di sesi lain.
            abort_if($request->header('X-Inertia') && ! $request->isMethodSafe(), 403, 'Akun tidak aktif. Periksa status akun sebelum melanjutkan.');

            return redirect()->route('auth.pending');
        }

        return $next($request);
    }
}
