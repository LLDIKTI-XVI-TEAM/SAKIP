<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Jalankan sebelum setup trait; hook bawaan RefreshDatabase dapat menutupi method parent.
        if (array_intersect([RefreshDatabase::class, DatabaseMigrations::class], class_uses_recursive(static::class))) {
            $this->assertDisposableDatabase($app);
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Build asset diverifikasi job terpisah; test HTTP tidak memerlukan manifest Vite.
        $this->withoutVite();
    }

    /**
     * Tolak reset sebelum RefreshDatabase menyentuh schema yang belum dipastikan disposable.
     */
    private function assertDisposableDatabase(Application $app): void
    {
        if (! $app->environment('testing') || $app->configurationIsCached()) {
            throw new RuntimeException('Reset test memerlukan APP_ENV=testing tanpa config cache.');
        }

        if ((string) env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1') {
            throw new RuntimeException('Set SAKIP_TEST_ALLOW_DATABASE_RESET=1 hanya untuk PostgreSQL testing disposable.');
        }

        $connection = DB::connection();

        if ($connection->getDriverName() !== 'pgsql'
            || $connection->getDatabaseName() !== 'sakip_test'
            || $connection->getConfig('username') !== 'sakip_test'
            || $connection->getConfig('read') !== null
            || $connection->getConfig('write') !== null) {
            throw new RuntimeException('Reset test hanya diizinkan pada koneksi tunggal pgsql sakip_test dengan akun sakip_test.');
        }

        // Periksa identitas koneksi tulis sebenarnya, termasuk bila konfigurasi memakai DB_URL.
        $identity = $connection->selectOne('select current_database() as database, current_user as username', [], false);

        if ($identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
            throw new RuntimeException('Identitas PostgreSQL aktual bukan target testing disposable yang diizinkan.');
        }
    }
}
