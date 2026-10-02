<?php

use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Jadwal\ShowJadwalEditor;
use App\Actions\Periode\ListPeriode;
use App\Actions\Periode\ReplaceFinalPeriode;
use App\Actions\Periode\SavePeriode;
use App\Actions\Renstra\ChangeRenstraStatus;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $connection = DB::connection();
    $identity = $connection->selectOne('select current_database() as database, current_user as username, pg_backend_pid() as pid');
    if (! $app->environment('testing') || $app->configurationIsCached() || (string) env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1'
        || $connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'sakip_test'
        || $connection->getConfig('username') !== 'sakip_test' || $connection->getConfig('read') !== null || $connection->getConfig('write') !== null
        || $identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
        throw new RuntimeException('Worker hanya boleh memakai PostgreSQL testing disposable.');
    }
    DB::statement("SET lock_timeout = '20s'");
    DB::statement("SET statement_timeout = '25s'");
    $payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
    $actor = User::findOrFail($payload['actor_id']);
    if ($payload['pause_before_save'] ?? false) {
        $model = match ($payload['operation']) {
            'save' => JadwalTahunan::class,
            'deactivate' => Renstra::class,
            default => Periode::class,
        };
        $paused = false;
        $model::saving(function () use (&$paused): void {
            if ($paused) {
                return;
            }
            $paused = true;
            fwrite(STDOUT, "SAVING\n");
            fflush(STDOUT);
            if (trim((string) fgets(STDIN)) !== 'SAVE') {
                throw new RuntimeException('Barrier save tidak diberikan.');
            }
        });
    }
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier worker tidak diberikan.');
    }
    try {
        $result = match ($payload['operation']) {
            'save' => app(SaveJadwalDraft::class)->handle($actor, $payload['data'], isset($payload['id']) ? (new JadwalTahunan)->forceFill(['id' => $payload['id']]) : null),
            'master' => app(SavePeriode::class)->handle($actor, $payload['data'], isset($payload['id']) ? (new Periode)->forceFill(['id' => $payload['id']]) : null),
            'swap' => app(ReplaceFinalPeriode::class)->handle($actor, $payload['data']),
            'deactivate' => app(ChangeRenstraStatus::class)->execute(Renstra::findOrFail($payload['id']), $actor, 'deactivate', $payload['expected_state']),
            'show' => app(ShowJadwalEditor::class)->handle($actor, (new JadwalTahunan)->forceFill(['id' => $payload['id']])),
            'list' => app(ListPeriode::class)->handle($actor, ['q' => 'Kandidat']),
        };
        $result = match ($payload['operation']) {
            'show' => ['revisi' => $result['jadwal']['revisi'], 'penutupan' => $result['jadwal']['penutupan']],
            'list' => $result['current_final'],
            default => 'changed',
        };
    } catch (ValidationException $exception) {
        $result = array_key_first($exception->errors());
    } catch (AuthorizationException) {
        $result = 'denied';
    }
    fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    $sqlState = $exception instanceof QueryException ? ' SQLSTATE:'.($exception->errorInfo[0] ?? 'unknown') : '';
    fwrite(STDERR, get_class($exception).$sqlState."\n");
    exit(1);
}
