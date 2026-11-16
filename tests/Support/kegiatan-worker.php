<?php

use App\Actions\Kegiatan\SimpanKegiatan;
use App\Actions\Kegiatan\UbahStatusKegiatan;
use App\Models\Kegiatan;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $c = DB::connection();
    $identity = $c->selectOne('select current_database() as database, current_user as username, pg_backend_pid() as pid');
    if (! $app->environment('testing') || $app->configurationIsCached() || env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1'
        || $c->getDriverName() !== 'pgsql' || $c->getDatabaseName() !== 'sakip_test' || $c->getConfig('username') !== 'sakip_test'
        || $c->getConfig('read') !== null || $c->getConfig('write') !== null
        || $identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
        throw new RuntimeException('Worker memerlukan PostgreSQL testing disposable terverifikasi.');
    }
    DB::statement("SET lock_timeout = '15s'");
    DB::statement("SET statement_timeout = '20s'");
    $payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($payload['actor_id']);
    $ubahStatus = ($payload['operation'] ?? null) === 'ubah_status';
    $kegiatan = $ubahStatus ? Kegiatan::findOrFail($payload['id']) : null;
    $code = isset($payload['id']) ? 'kegiatan:update' : 'kegiatan:create';
    if (! app(PermissionResolver::class)->allows($actor, $code, $kegiatan?->unit_id ?? $payload['data']['unit_id'])) {
        throw new RuntimeException('Fixture harus berizin sebelum barrier.');
    }
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier GO tidak diterima.');
    }
    if ($kegiatan) {
        app(UbahStatusKegiatan::class)->handle($actor, $kegiatan, $payload['data']);
    } else {
        app(SimpanKegiatan::class)->handle($actor, $payload['data'], $payload['id'] ?? null);
    }
    $result = ['outcome' => 'saved'];
} catch (AuthorizationException) {
    $result = ['outcome' => 'denied'];
} catch (ValidationException $exception) {
    $result = ['outcome' => 'validation', 'fields' => array_keys($exception->errors())];
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
