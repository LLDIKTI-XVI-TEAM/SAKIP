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
        Gate::policy(\App\Models\Unit::class, \App\Policies\UnitPolicy::class);
        Gate::policy(\App\Models\Regulasi::class, \App\Policies\RegulasiPolicy::class);

        foreach (\App\Support\PermissionCodes::activeIssueCodes() as $code) {
            Gate::define($code, function (User $user, ?string $unitId = null) use ($code) {
                return app(PermissionResolver::class)->allows($user, $code, $unitId);
            });
        }
    }
}
