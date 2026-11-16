<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\EndLocalSession;
use App\Services\Auth\KeycloakIdentityProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ProcessSsoLogout
{
    public function __invoke(Request $request, EndLocalSession $endSession): Response
    {
        // Cleanup lokal selesai sebelum konfigurasi/provider dapat gagal.
        $endSession->handle($request);
        try {
            return Inertia::location(app(KeycloakIdentityProvider::class)->ssoLogoutUrl());
        } catch (Throwable $exception) {
            Log::warning('Logout SSO tidak tersedia; session lokal telah dihapus.', ['category' => get_class($exception)]);
            Inertia::flash('logoutNotice', 'sso_unavailable');

            return redirect()->route('auth.logged-out');
        }
    }
}
