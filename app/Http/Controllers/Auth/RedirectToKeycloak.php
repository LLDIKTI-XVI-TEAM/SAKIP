<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\KeycloakIdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectToKeycloak
{
    public function __invoke(Request $request): Response
    {
        try {
            $provider = app(KeycloakIdentityProvider::class);
            $callback = parse_url(config('services.keycloak.redirect'));
            $loopback = ['localhost', '127.0.0.1', '[::1]'];
            if (app()->environment('local') && is_array($callback)
                && in_array($request->getHost(), $loopback, true)
                && in_array($callback['host'] ?? null, $loopback, true)
                && $request->getHost() !== $callback['host']) {
                // Cookie tiap hostname berbeda; mulai state/PKCE hanya pada origin callback yang tervalidasi.
                $origin = $callback['scheme'].'://'.$callback['host'].(isset($callback['port']) ? ':'.$callback['port'] : '');

                return Inertia::location($origin.'/login');
            }

            // Navigasi Inertia setelah sesi habis harus berpindah halaman ke origin SSO.
            return Inertia::location($provider->redirect());
        } catch (Throwable $e) {
            Log::warning('SSO tidak dapat dimulai.', ['category' => get_class($e)]);

            return redirect()->route('auth.error');
        }
    }
}
