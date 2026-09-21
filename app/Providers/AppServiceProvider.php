<?php

namespace App\Providers;

use App\Services\Auth\KeycloakIdentityProvider;
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
        //
    }
}
