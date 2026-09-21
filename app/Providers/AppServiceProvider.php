<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(KeycloakIdentityProvider::class, fn ($app) => KeycloakIdentityProvider::forRequest($app['request']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('akses:update', function (User $user) {
            return app(PermissionResolver::class)->allows($user, 'akses:update');
        });
    }
}
