<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('akses:update', function (User $user) {
            if ($user->hasRole(['superadmin', 'admin'])) {
                return true;
            }

            try {
                return $user->hasPermissionTo('akses:update');
            } catch (\Throwable) {
                return false;
            }
        });
    }
}
