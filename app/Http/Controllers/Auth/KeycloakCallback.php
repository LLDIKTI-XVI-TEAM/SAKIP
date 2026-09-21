<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ProvisionKeycloakUser;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Auth\KeycloakTokenValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Throwable;

class KeycloakCallback
{
    public function __invoke(Request $request, ProvisionKeycloakUser $provision, KeycloakTokenValidator $validator): RedirectResponse
    {
        try {
            $identity = app(KeycloakIdentityProvider::class)->identity($validator);
            $user = $provision->handle($identity);
            Auth::login($user);
            $request->session()->regenerate();
            Inertia::clearHistory();
            Log::info('Autentikasi SSO berhasil.', ['category' => 'login_success', 'actor_id' => $user->id, 'correlation_id' => (string) Str::uuid()]);

            return redirect()->route($user->is_active ? 'dashboard' : 'auth.pending');
        } catch (Throwable $e) {
            $request->session()->forget(['state', 'code_verifier', 'oidc_nonce', 'oidc_started_at']);
            Log::warning('Autentikasi SSO gagal.', ['category' => get_class($e)]);

            return redirect()->route('auth.error');
        }
    }
}
