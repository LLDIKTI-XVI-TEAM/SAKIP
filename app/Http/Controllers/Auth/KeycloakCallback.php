<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ProvisionKeycloakUser;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Auth\KeycloakTokenValidator;
use App\Services\Authorization\RoleCatalog;
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
        // Ambil sekali sebelum provider: failure tidak boleh mewariskan intent callback.
        $recovery = $request->session()->pull('auth_recovery_requested') === true;
        try {
            $identity = app(KeycloakIdentityProvider::class)->identity($validator);
            $user = $provision->handle($identity);
            // Selesaikan lookup landing sebelum identity sesi berubah jika query gagal.
            $landing = 'auth.pending';
            if ($user->status === 'aktif') {
                if ($recovery) {
                    $landing = 'auth.recovered';
                } elseif ($user->roles()->whereIn('kode', RoleCatalog::codes())->where('roles.aktif', true)->exists()) {
                    $landing = 'dashboard';
                }
            }

            Auth::login($user);
            $request->session()->regenerate();
            Inertia::clearHistory();
            Log::info('Autentikasi SSO berhasil.', ['category' => 'login_success', 'actor_id' => $user->id, 'correlation_id' => (string) Str::uuid()]);

            if ($recovery && $user->status !== 'aktif') {
                Inertia::flash('authRecoveryNotice', 'no_replay');
            }

            return redirect()->route($landing);
        } catch (Throwable $e) {
            if ($recovery) {
                $request->session()->flash('auth_recovery_retry', true);
            }
            $request->session()->forget(['state', 'code_verifier', 'oidc_nonce', 'oidc_started_at']);
            Log::warning('Autentikasi SSO gagal.', ['category' => get_class($e)]);

            return redirect()->route('auth.error');
        }
    }
}
