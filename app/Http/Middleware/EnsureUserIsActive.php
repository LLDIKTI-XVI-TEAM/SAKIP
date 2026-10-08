<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Baca status saat request, termasuk session yang dibentuk sebelum penonaktifan.
        $actor = $request->user()?->fresh();
        if ($actor?->status !== 'aktif') {
            return $this->rejectInactive($request, $actor);
        }

        return $next($request);
    }

    /** Jalur mutasi sensitif dapat mencatat penolakan sebelum respons fail-closed dikirim. */
    protected function rejectInactive(Request $request, ?User $actor): Response
    {
        // Respons tanpa navigasi menjaga draft form saat akun dinonaktifkan di sesi lain.
        abort_if($request->header('X-Inertia') && ! $request->isMethodSafe(), 403, 'Akun tidak aktif. Periksa status akun sebelum melanjutkan.');

        return redirect()->route('auth.pending');
    }
}
