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
        $recovery = $request->query('recovery') === '1';
        $request->session()->forget(['auth_recovery_requested', 'auth_recovery_retry']);
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

                return Inertia::location($origin.'/login'.($recovery ? '?recovery=1' : ''));
            }

            if ($recovery) {
                $request->session()->put('auth_recovery_requested', true);
            }

            // Recovery hanya dimulai lewat navigasi login eksplisit, tanpa payload mutation.
            return Inertia::location($provider->redirect());
        } catch (Throwable $e) {
            $request->session()->forget('auth_recovery_requested');
            if ($recovery) {
                $request->session()->flash('auth_recovery_retry', true);
            }
            Log::warning('SSO tidak dapat dimulai.', ['category' => get_class($e)]);

            return redirect()->route('auth.error');
        }
    }
}
