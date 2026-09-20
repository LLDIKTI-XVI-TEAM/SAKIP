<?php

namespace App\Providers;

use App\Models\Regulasi;
use App\Models\User;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'regulasi' => Regulasi::class,
        ]);

        Gate::define('view-verifikasi', fn (User $user): bool => $user->hasAnyRole([
            'superadmin',
            'perencanaan',
        ]));

        Gate::define('mutate-pengukuran', fn (User $user): bool => $user->hasAnyRole(['superadmin', 'perencanaan'])
            || $user->penugasanIndikators()->where('is_active', true)->exists());
    }
}
