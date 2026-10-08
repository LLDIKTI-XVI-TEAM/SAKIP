<?php

use App\Actions\Jadwal\ActivateJadwal;
use App\Actions\Jadwal\SaveJadwalDraft;
use App\Actions\Pengaturan\UpdateStoragePolicyAction;
use App\Actions\Perencanaan\ChangeIndicatorFormula;
use App\Actions\Perencanaan\DestroyIndikator;
use App\Actions\Perencanaan\PindahUnitIndikator;
use App\Actions\Perencanaan\StoreIndikator;
use App\Actions\Periode\SavePeriode;
use App\Actions\PerjanjianKinerja\DeleteBerkasPerjanjianKinerja;
use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\RenstraPk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Worker race aktivasi: satu operasi produksi per proses pada PostgreSQL disposable yang sama dengan proses test.
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
    DB::statement("SET lock_timeout = '20s'");
    DB::statement("SET statement_timeout = '25s'");
    if (isset($payload['now'])) {
        Carbon::setTestNow($payload['now']);
        CarbonImmutable::setTestNow($payload['now']);
    }
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier GO tidak diterima.');
    }
    if (isset($payload['pause_on'])) {
        // Berhenti tepat sebelum SQL penanda pertama, sambil tetap memegang lock yang sudah diperoleh.
        $paused = false;
        $c->beforeExecuting(function (string $sql) use (&$paused, $payload): void {
            if (! $paused && str_contains($sql, $payload['pause_on'][0]) && str_contains($sql, $payload['pause_on'][1])) {
                $paused = true;
                fwrite(STDOUT, "PAUSED\n");
                fflush(STDOUT);
                if (trim((string) fgets(STDIN)) !== 'CONTINUE') {
                    throw new RuntimeException('Barrier CONTINUE tidak diterima.');
                }
            }
        });
    }
    $actor = User::findOrFail($payload['actor_id']);
    $data = $payload['data'] ?? [];
    $result = match ($payload['operation']) {
        'activate' => app(ActivateJadwal::class)->handle($actor, $payload['id'], $data),
        'save_target' => app(SaveTargetTahunan::class)->handle($actor, $payload['id'], $payload['year'], $data),
        'archive' => app(DestroyIndikator::class)->handle($actor, IndikatorKinerja::findOrFail($payload['id']), 'Arsip fixture konkurensi'),
        'formula' => app(ChangeIndicatorFormula::class)->handle($actor, IndikatorKinerja::findOrFail($payload['id']), $data),
        'unit' => app(PindahUnitIndikator::class)->handle($actor, IndikatorKinerja::findOrFail($payload['id']), $data),
        'store_indikator' => app(StoreIndikator::class)->handle($actor, $data),
        'delete_berkas' => app(DeleteBerkasPerjanjianKinerja::class)->handle(RenstraPk::findOrFail($payload['id']), Berkas::findOrFail($payload['berkas_id']), 'Hapus lampiran fixture konkurensi', $actor),
        'save_jadwal' => app(SaveJadwalDraft::class)->handle($actor, $data, (new JadwalTahunan)->forceFill(['id' => $payload['id']])),
        'save_periode' => app(SavePeriode::class)->handle($actor, $data, (new Periode)->forceFill(['id' => $payload['id']])),
        'storage' => app(UpdateStoragePolicyAction::class)->handle($actor, $data),
    };
    $result = ['outcome' => 'changed', 'changed' => is_array($result) ? ($result['changed'] ?? null) : null];
} catch (ValidationException $exception) {
    $result = ['outcome' => 'validation', 'fields' => array_keys($exception->errors())];
} catch (AuthorizationException) {
    $result = ['outcome' => 'denied'];
} catch (HttpException $exception) {
    $result = ['outcome' => 'http', 'status' => $exception->getStatusCode()];
} catch (Throwable $exception) {
    $result = ['outcome' => 'error', 'type' => get_class($exception), 'message' => $exception->getMessage()];
}
fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
