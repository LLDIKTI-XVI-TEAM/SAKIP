<?php

use App\Actions\Access\AssignRole;
use App\Actions\Access\ChangeRolePermission;
use App\Actions\Access\CreateDeny;
use App\Actions\Access\RevokeDeny;
use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
    try {
        $assignment = isset($argv[3]) ? json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR) : [];
        $result = match ($argv[1]) {
            'change-role-permission' => app(ChangeRolePermission::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['role_id'], $assignment['permission_id'], $assignment['operation'], $assignment['alasan'], $assignment['expected_state']),
            'assign-role' => app(AssignRole::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['target_id'], $assignment['role_id'], $assignment['alasan'], $assignment['expected_assignment']),
            'create-deny' => app(CreateDeny::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['target_id'], $assignment['permission_id'], $assignment['unit_id'], $assignment['alasan']),
            'revoke-deny' => app(RevokeDeny::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['deny_id'], $assignment['alasan']),
            'provision' => app(ProvisionKeycloakUser::class)->handle(['subject' => $identity, 'nama' => 'Fixture Bersamaan', 'email' => 'concurrent@example.test'])->id,
            'bootstrap' => app(BootstrapSuperadmin::class)->handle($identity, 'Operator Pengujian', 'Fixture konkurensi bootstrap', 'test-process:'.getmypid()),
            default => throw new InvalidArgumentException('Operasi worker tidak dikenal.'),
        };
        $result = match ($argv[1]) {
            'create-deny' => 'created',
            'revoke-deny' => 'revoked',
            default => $result,
        };
    } catch (ValidationException $exception) {
        $expectedField = match ($argv[1]) {
            'change-role-permission' => 'expected_state',
            'assign-role' => 'expected_assignment',
            'create-deny' => 'permission_id',
            'revoke-deny' => 'deny_id',
            default => null,
        };
        if ($expectedField === null || ! isset($exception->errors()[$expectedField])) {
            throw $exception;
        }
        $result = match ($argv[1]) {
            'create-deny' => 'duplicate',
            'revoke-deny' => 'stale',
            default => 'conflict',
        };
    } catch (AuthorizationException $exception) {
        if ($argv[1] !== 'change-role-permission') {
            throw $exception;
        }
        $result = 'denied';
    }
    fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    // Jangan mencetak SQL, konfigurasi koneksi, atau kredensial dari exception.
    fwrite(STDERR, get_class($exception)."\n");
    exit(1);
}
