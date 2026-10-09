<?php

namespace App\Providers;

use App\Models\Regulasi;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Unit;
use App\Models\User;
use App\Policies\RegulasiPolicy;
use App\Policies\RencanaAksiPolicy;
use App\Policies\RenstraPkPolicy;
use App\Policies\RenstraPolicy;
use App\Policies\UnitPolicy;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        Relation::morphMap([
            'renstra_pk' => RenstraPk::class,
            'rencana_aksi' => RencanaAksi::class,
        ]);

        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Regulasi::class, RegulasiPolicy::class);
        Gate::policy(Renstra::class, RenstraPolicy::class);
        Gate::policy(RenstraPk::class, RenstraPkPolicy::class);
        Gate::policy(RencanaAksi::class, RencanaAksiPolicy::class);

        foreach (PermissionCatalog::codes() as $code) {
            Gate::define($code, function (User $user, ?string $unitId = null) use ($code) {
                return app(PermissionResolver::class)->allows($user, $code, $unitId);
            });
        }
    }
}
