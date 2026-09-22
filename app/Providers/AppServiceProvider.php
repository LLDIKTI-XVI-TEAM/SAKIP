<?php

namespace App\Providers;

use App\Models\Regulasi;
use App\Models\Unit;
use App\Models\User;
use App\Policies\RegulasiPolicy;
use App\Policies\UnitPolicy;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Authorization\PermissionCatalog;
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
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Regulasi::class, RegulasiPolicy::class);

        foreach (PermissionCatalog::codes() as $code) {
            Gate::define($code, function (User $user, ?string $unitId = null) use ($code) {
                return app(PermissionResolver::class)->allows($user, $code, $unitId);
            });
        }
    }
}
