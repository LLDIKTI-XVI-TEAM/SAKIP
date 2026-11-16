<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProtectSessionResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::encryptHistory();
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        // Form Inertia memerlukan referer internal untuk redirect validasi ke halaman asal.
        // URL callback OIDC tidak boleh menjadi referer, termasuk ke origin aplikasi sendiri.
        $response->headers->set('Referrer-Policy', $request->routeIs('auth.callback') ? 'no-referrer' : 'same-origin');

        return $response;
    }
}
