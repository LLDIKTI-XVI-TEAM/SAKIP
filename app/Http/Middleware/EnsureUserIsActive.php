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
        if (! $request->user()?->fresh()?->is_active) {
            return redirect()->route('auth.pending');
        }

        return $next($request);
    }
}
