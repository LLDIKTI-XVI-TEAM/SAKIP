<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\KeycloakIdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProcessLogout
{
    public function __invoke(Request $request): Response
    {
        $actorId = $request->user()?->id;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();
        Log::info('Session SAKIP diakhiri.', ['category' => 'logout_local', 'actor_id' => $actorId, 'correlation_id' => (string) Str::uuid()]);
        try {
            $provider = app(KeycloakIdentityProvider::class);
            $landing = config('services.keycloak.post_logout_redirect');
            if (! is_string($landing) || ! filter_var($landing, FILTER_VALIDATE_URL)) {
                throw new \UnexpectedValueException('Landing logout belum dikonfigurasi.');
            }

            return Inertia::location($provider->getLogoutUrl($landing, config('services.keycloak.client_id')));
        } catch (Throwable $e) {
            Log::warning('Logout SSO tidak tersedia; session lokal telah dihapus.', ['category' => get_class($e)]);

            return redirect()->route('auth.logged-out');
        }
    }
}
