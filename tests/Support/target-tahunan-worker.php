<?php

use App\Actions\Perencanaan\DestroyIndikator;
use App\Actions\Regulasi\DeleteRegulasiAction;
use App\Actions\Regulasi\DeleteRegulasiAttachmentAction;
use App\Actions\Renstra\UpdateRenstraAction;
use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;
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
        throw new RuntimeException('Worker membutuhkan PostgreSQL disposable terverifikasi.');
    }
    $payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
    DB::select("select set_config('lock_timeout', ?, false)", [$payload['timeout'] ?? '20s']);
    DB::statement("SET statement_timeout = '25s'");
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier GO tidak diterima.');
    }
    if ($payload['pause_before_renstra'] ?? false) {
        $paused = false;
        $c->beforeExecuting(function (string $sql) use (&$paused): void {
            if (! $paused && str_contains($sql, 'from "renstras"') && str_contains($sql, 'for share')) {
                $paused = true;
                fwrite(STDOUT, "PAUSED_BEFORE_RENSTRA\n");
                fflush(STDOUT);
                if (trim((string) fgets(STDIN)) !== 'CONTINUE') {
                    throw new RuntimeException('Barrier Renstra tidak diterima.');
                }
            }
        });
    }
    if ($payload['operation'] === 'migration') {
        $paused = false;
        $c->beforeExecuting(function (string $sql) use (&$paused, $payload): void {
            if (! $paused && str_starts_with($sql, 'ALTER TABLE target_kinerjas')) {
                $paused = true;
                $ms = DB::selectOne("select setting::bigint as ms from pg_settings where name='lock_timeout'")->ms;
                fwrite(STDOUT, 'SCANNED:'.$ms."\n");
                fflush(STDOUT);
                if (($payload['pause'] ?? false) && trim((string) fgets(STDIN)) !== 'CONTINUE') {
                    throw new RuntimeException('Barrier DDL tidak diterima.');
                }
            }
        });
        $migration = require database_path('migrations/2026_10_04_000001_add_baseline_to_target_kinerjas.php');
        $direction = $payload['direction'];
        $migration->$direction();
        $result = ['outcome' => 'changed', 'timeout_after' => DB::selectOne("select current_setting('lock_timeout') as value")->value];
    } elseif ($payload['operation'] === 'sql') {
        // SQL hanya berasal dari fixture test yang dikontrol proses induk, bukan input aplikasi.
        DB::statement($payload['sql'], $payload['bindings'] ?? []);
        $result = ['outcome' => 'changed'];
    } elseif (in_array($payload['operation'], ['delete_regulasi', 'delete_attachment'], true)) {
        $actor = User::findOrFail($payload['actor_id']);
        $regulasi = Regulasi::findOrFail($payload['regulasi_id']);
        if ($payload['operation'] === 'delete_attachment') {
            app(DeleteRegulasiAttachmentAction::class)->handle($actor, $regulasi, Berkas::findOrFail($payload['berkas_id']), 'Hapus lampiran fixture konkurensi.');
        } else {
            app(DeleteRegulasiAction::class)->handle($actor, $regulasi, 'Hapus regulasi fixture konkurensi.');
        }
        $result = ['outcome' => 'deleted'];
    } elseif (in_array($payload['operation'], ['save', 'show', 'shrink', 'archive'], true)) {
        $actor = User::findOrFail($payload['actor_id']);
        $result = match ($payload['operation']) {
            'save' => app(SaveTargetTahunan::class)->handle($actor, $payload['indicator_id'], $payload['year'], $payload['data']),
            'show' => app(ShowTargetTahunan::class)->handle($actor, $payload['indicator_id'], $payload['year']),
            'shrink' => app(UpdateRenstraAction::class)->handle($actor, Renstra::findOrFail($payload['renstra_id']), $payload['data'])->only(['id', 'tahun_selesai']),
            'archive' => app(DestroyIndikator::class)->handle($actor, IndikatorKinerja::findOrFail($payload['indicator_id']), 'Arsip fixture'),
        };
    } else {
        throw new RuntimeException('Operasi worker tidak dikenal.');
    }
    fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (ValidationException $exception) {
    fwrite(STDOUT, 'RESULT:'.json_encode(['outcome' => 'validation', 'fields' => array_keys($exception->errors()), 'status' => $exception->status], JSON_THROW_ON_ERROR)."\n");
} catch (AuthorizationException $exception) {
    fwrite(STDOUT, 'RESULT:'.json_encode(['outcome' => 'denied'], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, 'RESULT:'.json_encode(['outcome' => 'error', 'type' => get_class($exception), 'message' => $exception->getMessage(), 'timeout_after' => DB::selectOne("select current_setting('lock_timeout') as value")->value], JSON_THROW_ON_ERROR)."\n");
}
