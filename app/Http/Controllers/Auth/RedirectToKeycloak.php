<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\KeycloakIdentityProvider;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RedirectToKeycloak
{
    public function __invoke(): Response
    {
        try {
            // Navigasi Inertia setelah sesi habis harus berpindah halaman ke origin SSO.
            return Inertia::location(app(KeycloakIdentityProvider::class)->redirect());
        } catch (Throwable $e) {
            Log::warning('SSO tidak dapat dimulai.', ['category' => get_class($e)]);

            return redirect()->route('auth.error');
        }
    }
}
