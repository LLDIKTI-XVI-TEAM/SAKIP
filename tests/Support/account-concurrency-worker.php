<?php

use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $connection = DB::connection();
    $identity = $connection->selectOne('select current_database() as database, current_user as username, pg_backend_pid() as pid');
    if (! $app->environment('testing') || $app->configurationIsCached()
        || (string) env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1'
        || $connection->getDriverName() !== 'pgsql'
        || $connection->getDatabaseName() !== 'sakip_test'
        || $connection->getConfig('username') !== 'sakip_test'
        || $connection->getConfig('read') !== null || $connection->getConfig('write') !== null
        || $identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
        throw new RuntimeException('Worker hanya boleh memakai PostgreSQL testing disposable.');
    }
    DB::statement("SET lock_timeout = '15s'");
    DB::statement("SET statement_timeout = '20s'");
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier worker tidak diberikan.');
    }
    $identity = $argv[2];
    $result = match ($argv[1]) {
        'provision' => app(ProvisionKeycloakUser::class)->handle(['subject' => $identity, 'nama' => 'Fixture Bersamaan', 'email' => 'concurrent@example.test'])->id,
        'bootstrap' => app(BootstrapSuperadmin::class)->handle($identity, 'Operator Pengujian', 'Fixture konkurensi bootstrap', 'test-process:'.getmypid()),
        default => throw new InvalidArgumentException('Operasi worker tidak dikenal.'),
    };
    fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    // Jangan mencetak SQL, konfigurasi koneksi, atau kredensial dari exception.
    fwrite(STDERR, get_class($exception)."\n");
    exit(1);
}
