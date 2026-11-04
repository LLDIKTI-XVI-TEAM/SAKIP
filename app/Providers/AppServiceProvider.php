<?php

namespace App\Providers;

use App\Jobs\KirimNotifikasiEksternal;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\NotifikasiPengiriman;
use App\Models\PeriodeJadwal;
use App\Models\Regulasi;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Policies\RegulasiPolicy;
use App\Policies\RencanaAksiPolicy;
use App\Policies\RenstraPkPolicy;
use App\Policies\RenstraPolicy;
use App\Policies\UnitPolicy;
use App\Services\Auth\KeycloakIdentityProvider;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Services\Notifikasi\PenyediaKanalNotifikasi;
use App\Services\Notifikasi\PenyediaLog;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Queue\Events\JobRetryRequested;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PenyediaKanalNotifikasi::class, PenyediaLog::class);
        $this->app->bind(KeycloakIdentityProvider::class, fn ($app) => KeycloakIdentityProvider::forRequest($app['request']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::createPayloadUsing(static function ($connection, $queue, $payload): array {
            $prefix = KirimNotifikasiEksternal::class.':';
            $name = $payload['displayName'] ?? '';

            return str_starts_with($name, $prefix) ? ['notifikasi_pengiriman_id' => substr($name, strlen($prefix))] : [];
        });
        Event::listen(JobRetryRequested::class, static function ($event): void {
            $payload = json_decode($event->job->payload, true);
            if ($event->job->connection === 'notifikasi' && isset($payload['notifikasi_pengiriman_id'])) {
                NotifikasiPengiriman::whereKey($payload['notifikasi_pengiriman_id'])->where('status', 'gagal')->where('kode_hasil', 'percobaan_habis')
                    ->update(['status' => 'antre', 'kode_hasil' => null, 'percobaan_siklus' => 0]);
            }
        });
        Relation::morphMap([
            'renstra_pk' => RenstraPk::class,
            'jadwal_tahunan' => JadwalTahunan::class,
            'jadwal_periode' => PeriodeJadwal::class,
        ]);

        Gate::policy(Kegiatan::class, KegiatanPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Regulasi::class, RegulasiPolicy::class);
        Gate::policy(RencanaAksi::class, RencanaAksiPolicy::class);
        Gate::policy(Renstra::class, RenstraPolicy::class);
        Gate::policy(RenstraPk::class, RenstraPkPolicy::class);

        foreach (PermissionCatalog::codes() as $code) {
            Gate::define($code, function (User $user, ?string $unitId = null) use ($code) {
                return app(PermissionResolver::class)->allows($user, $code, $unitId);
            });
        }
    }
}
