<?php

namespace Tests\Feature\TargetTahunan;

use App\Actions\Access\CreateDeny;
use App\Actions\Perencanaan\ChangeIndicatorFormula;
use App\Actions\Perencanaan\DestroyIndikator;
use App\Actions\Renstra\UpdateRenstraAction;
use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\Permission;
use App\Models\Regulasi;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanConcurrencyTest extends TestCase
{
    use DatabaseMigrations, TargetTahunanFixtures;

    private array $workers = [];

    /** Data committed antarproses; cleanup fresh karena audit append-only dan down lossless. */
    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            foreach ($this->workers as $worker) {
                $worker['process']->stop(0);
            }
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->assertDisposableDatabase($this->app);
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            RefreshDatabaseState::$migrated = false;
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000001_add_baseline_to_target_kinerjas.php');
    }

    private function row(): array
    {
        $actor = $this->calendarActor();
        $indicator = $this->targetIndicator($actor);
        $id = (string) Str::uuid();
        DB::table('target_kinerjas')->insert(['id' => $id, 'indikator_kinerja_id' => $indicator->id, 'tahun' => 2026, 'target_tahunan' => '85.25']);

        return [$id, $indicator];
    }

    #[DataProvider('directions')]
    public function test_scan_and_ddl_exclude_concurrent_target_and_parent_writes(string $direction): void
    {
        if ($direction === 'up') {
            $this->migration()->down();
        }
        [$id, $indicator] = $this->row();
        $migration = $this->start(['operation' => 'migration', 'direction' => $direction, 'pause' => true, 'timeout' => '10s']);
        $this->go($migration);
        $this->until(fn () => str_contains($migration['process']->getOutput(), 'SCANNED:5000'), [$migration]);
        $writer = $this->start(['operation' => 'sql', 'sql' => 'UPDATE target_kinerjas SET target_tahunan=90 WHERE id=?', 'bindings' => [$id]]);
        $parent = $this->start(['operation' => 'sql', 'sql' => 'UPDATE indikator_kinerjas SET nama=? WHERE id=?', 'bindings' => ['Indikator baru', $indicator->id]]);
        $this->go($writer);
        $this->go($parent);
        $this->blockedBy($writer, $migration['pid']);
        $this->blockedBy($parent, $migration['pid']);
        $migration['input']->write("CONTINUE\n");
        $this->assertSame(['outcome' => 'changed', 'timeout_after' => '10s'], $this->workerResult($migration));
        $this->assertSame('changed', $this->workerResult($writer)['outcome']);
        $this->assertSame('changed', $this->workerResult($parent)['outcome']);
        $this->assertSame($direction === 'up', Schema::hasColumn('target_kinerjas', 'baseline'));
        $this->assertEquals(90, DB::table('target_kinerjas')->where('id', $id)->value('target_tahunan'));
    }

    public static function directions(): array
    {
        return [['up'], ['down']];
    }

    #[DataProvider('heldLocks')]
    public function test_up_and_down_verify_finite_lock_timeout(string $direction, string $timeout): void
    {
        if ($direction === 'up') {
            $this->migration()->down();
        }
        [$id] = $this->row();
        $before = DB::table('target_kinerjas')->where('id', $id)->first();
        DB::beginTransaction();
        DB::statement('LOCK TABLE target_kinerjas IN SHARE MODE');
        $worker = $this->start(['operation' => 'migration', 'direction' => $direction, 'timeout' => $timeout]);
        $started = microtime(true);
        $this->go($worker);
        $result = $this->workerResult($worker);
        $elapsed = microtime(true) - $started;
        DB::rollBack();
        $this->assertSame('error', $result['outcome']);
        $this->assertStringContainsString('lock timeout', $result['message']);
        $this->assertSame($timeout, $result['timeout_after']);
        // Toleransi scheduler, bukan batas durasi seluruh migrasi.
        $this->assertGreaterThan($timeout === '100ms' ? 0.07 : 4.5, $elapsed);
        $this->assertLessThan($timeout === '100ms' ? 3 : 9, $elapsed);
        $this->assertSame($direction === 'down', Schema::hasColumn('target_kinerjas', 'baseline'));
        $this->assertEquals($before, DB::table('target_kinerjas')->where('id', $id)->first());
    }

    public static function heldLocks(): array
    {
        return [['up', '0'], ['down', '10s'], ['up', '100ms'], ['down', '100ms']];
    }

    #[DataProvider('directions')]
    public function test_writer_committed_before_lock_is_seen_by_preflight(string $direction): void
    {
        if ($direction === 'up') {
            $this->migration()->down();
        }
        [$id] = $this->row();
        DB::beginTransaction();
        DB::table('target_kinerjas')->where('id', $id)->update($direction === 'up' ? ['target_tahunan' => 'NaN'] : ['baseline' => '74.2']);
        $worker = $this->start(['operation' => 'migration', 'direction' => $direction]);
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $result = $this->workerResult($worker);
        $this->assertSame('error', $result['outcome']);
        $this->assertStringContainsString($direction === 'up' ? 'annual_NaN' : 'baseline_terisi', $result['message']);
        $this->assertSame($direction === 'down', Schema::hasColumn('target_kinerjas', 'baseline'));
    }

    private function start(array $payload): array
    {
        $c = DB::connection();
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => $c->getConfig('host'), 'DB_PORT' => (string) $c->getConfig('port'), 'DB_DATABASE' => 'sakip_test', 'DB_USERNAME' => 'sakip_test', 'DB_PASSWORD' => (string) $c->getConfig('password'), 'SAKIP_TEST_ALLOW_DATABASE_RESET' => '1', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
        $input = new InputStream;
        $process = new Process([PHP_BINARY, '-d', 'variables_order=EGPCS', base_path('tests/Support/target-tahunan-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), $env, $input, 30);
        $process->start();
        $worker = ['process' => $process, 'input' => $input];
        $this->workers[] = $worker;
        $this->until(fn () => preg_match('/READY:(\d+)/', $process->getOutput()) === 1, [$worker]);
        preg_match('/READY:(\d+)/', $process->getOutput(), $match);

        return [...$worker, 'pid' => (int) $match[1]];
    }

    private function runtimeFixture(int $year = 2026): array
    {
        $permissions = ['indikator:read', 'target:update', 'indikator:update', 'indikator:delete', 'renstra:update', 'akses:update'];
        $first = $this->calendarActor($permissions);
        $second = $this->calendarActor($permissions);
        $indicator = $this->targetIndicator($first)->refresh();
        $state = app(ShowTargetTahunan::class)->handle($second, $indicator->id, $year);
        $data = ['baseline' => null, 'target_tahunan' => '76.25', 'expected_state' => $state['expected_state'], 'operation_id' => (string) Str::uuid()];

        return [$first, $second, $indicator, $data];
    }

    public function test_concurrent_create_rejects_stale_writer_without_duplicate_or_success_audit(): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture();
        DB::beginTransaction();
        app(SaveTargetTahunan::class)->handle($first, $indicator->id, 2026, $data);
        $worker = $this->start(['operation' => 'save', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2026, 'data' => [...$data, 'target_tahunan' => '90']]);
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $this->assertSame(['outcome' => 'validation', 'fields' => ['expected_state'], 'status' => 409], $this->workerResult($worker));
        $this->assertSame(1, TargetKinerja::count());
        $this->assertSame('76.250000000000', TargetKinerja::sole()->target_tahunan);
        $this->assertSame(1, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_reader_waits_for_writer_and_returns_one_coherent_state(): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture();
        DB::beginTransaction();
        app(SaveTargetTahunan::class)->handle($first, $indicator->id, 2026, $data);
        $worker = $this->start(['operation' => 'show', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2026]);
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $actual = $this->workerResult($worker);
        $this->assertSame('76.25', $actual['target_tahunan']);
        $this->assertSame(app(ShowTargetTahunan::class)->handle($second, $indicator->id, 2026), $actual);
        $this->assertNotSame($data['expected_state'], $actual['expected_state']);
    }

    #[DataProvider('commitOrders')]
    public function test_save_vs_renstra_shrink_preserves_range(bool $targetFirst): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture(2029);
        $renstra = $indicator->sasaranStrategis->renstra;
        $revision = ['tahun_selesai' => 2028, 'expected_state' => $renstra->stateToken()];
        DB::beginTransaction();
        if ($targetFirst) {
            app(SaveTargetTahunan::class)->handle($first, $indicator->id, 2029, $data);
            $worker = $this->start(['operation' => 'shrink', 'actor_id' => $second->id, 'renstra_id' => $renstra->id, 'data' => $revision]);
        } else {
            app(UpdateRenstraAction::class)->handle($first, $renstra, $revision);
            $worker = $this->start(['operation' => 'save', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2029, 'data' => $data]);
        }
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $this->assertSame(['outcome' => 'validation', 'fields' => [$targetFirst ? 'tahun_mulai' : 'tahun'], 'status' => 422], $this->workerResult($worker));
        $this->assertSame($targetFirst ? 2029 : 2028, $renstra->fresh()->tahun_selesai);
        $this->assertSame($targetFirst ? 1 : 0, TargetKinerja::count());
    }

    public static function commitOrders(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('commitOrders')]
    public function test_save_vs_archive_rechecks_state(bool $targetFirst): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture();
        DB::beginTransaction();
        if ($targetFirst) {
            app(SaveTargetTahunan::class)->handle($first, $indicator->id, 2026, $data);
            $worker = $this->start(['operation' => 'archive', 'actor_id' => $second->id, 'indicator_id' => $indicator->id]);
        } else {
            app(DestroyIndikator::class)->handle($first, $indicator, 'Arsip fixture');
            $worker = $this->start(['operation' => 'save', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2026, 'data' => $data]);
        }
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $result = $this->workerResult($worker);
        if (! $targetFirst) {
            $this->assertSame(['outcome' => 'validation', 'fields' => ['target_tahunan'], 'status' => 422], $result);
        }
        $this->assertSame('arsip', $indicator->fresh()->status);
        $this->assertSame($targetFirst ? 1 : 0, TargetKinerja::count());
        $this->assertSame($targetFirst ? 1 : 0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    #[DataProvider('contextChanges')]
    public function test_save_vs_parent_or_precision_change_reloads_context(bool $moveParent): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture();
        $changes = $moveParent
            ? ['sasaran_strategis_id' => SasaranStrategis::create(['renstra_id' => $indicator->sasaranStrategis->renstra_id, 'kode' => 'SS-B', 'deskripsi' => 'Sasaran kedua'])->id]
            : ['presisi' => 1];
        DB::beginTransaction();
        app(ChangeIndicatorFormula::class)->handle($first, $indicator, [...$changes, 'expected_updated_at' => $indicator->updated_at->toISOString(), 'return_to' => 'sasaran-indikator']);
        $worker = $this->start(['operation' => 'save', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2026, 'data' => $data]);
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $this->assertSame(['outcome' => 'validation', 'fields' => ['expected_state'], 'status' => 409], $this->workerResult($worker));
        $this->assertSame(0, TargetKinerja::count());
    }

    public static function contextChanges(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('regulasiOperations')]
    public function test_target_and_regulasi_deletion_share_lock_order(string $operation, bool $attachment): void
    {
        $permissions = ['indikator:read', 'target:update', 'regulasi:delete', 'berkas:delete'];
        $reader = $this->calendarActor($permissions);
        $deleter = $this->calendarActor($permissions);
        $indicator = $this->targetIndicator($reader)->refresh();
        $regulasi = Regulasi::create(['jenis' => 'kepmen', 'nomor' => 'TARGET-LOCK', 'tahun' => 2026, 'tentang' => 'Rujukan fixture target', 'created_by' => $deleter->id]);
        $renstra = $indicator->sasaranStrategis->renstra;
        $renstra->update(['regulasi_id' => $regulasi->id]);
        $indicator->update(['regulasi_id' => $regulasi->id, 'status' => $operation === 'show' ? 'arsip' : 'aktif']);
        $file = $regulasi->berkas()->create(['mode' => 'teks', 'isi_teks' => 'Lampiran fixture', 'uploaded_by' => $deleter->id]);
        $state = app(ShowTargetTahunan::class)->handle($reader, $indicator->id, 2026);
        $target = $this->start(['operation' => $operation, 'actor_id' => $reader->id, 'indicator_id' => $indicator->id, 'year' => 2026,
            'data' => ['baseline' => '74.2', 'target_tahunan' => '76.25', 'expected_state' => $state['expected_state'], 'operation_id' => (string) Str::uuid()], 'pause_before_renstra' => true]);
        $this->go($target);
        $this->until(fn () => str_contains($target['process']->getOutput(), 'PAUSED_BEFORE_RENSTRA'), [$target]);
        $deletion = $this->start(['operation' => $attachment ? 'delete_attachment' : 'delete_regulasi', 'actor_id' => $deleter->id,
            'regulasi_id' => $regulasi->id, 'berkas_id' => $file->id]);
        $this->go($deletion);
        // Action target benar-benar memegang Indikator/Sasaran; Action penghapus sedang menunggu lock Indikator itu.
        $this->blockedBy($deletion, $target['pid']);
        $target['input']->write("CONTINUE\n");
        $targetResult = $this->workerResult($target);
        $deleteResult = $this->workerResult($deletion);
        $this->assertNotSame('error', $targetResult['outcome'] ?? null, json_encode($targetResult, JSON_THROW_ON_ERROR));
        $this->assertNotSame('error', $deleteResult['outcome'], json_encode($deleteResult, JSON_THROW_ON_ERROR));

        $deleteAction = $attachment ? 'berkas.hapus' : 'regulasi.hapus';
        $objectId = $attachment ? $file->id : $regulasi->id;
        if ($operation === 'show') {
            $this->assertSame('Indikator arsip hanya dapat dibaca.', $targetResult['read_only_reason']);
            $this->assertFalse($targetResult['can']['update']);
            $this->assertNull($targetResult['target_tahunan']);
            $this->assertSame(['outcome' => 'deleted'], $deleteResult);
            $this->assertSame(0, TargetKinerja::count());
            $this->assertSame($attachment, Regulasi::whereKey($regulasi->id)->exists());
            if (! $attachment) {
                $this->assertNull($indicator->fresh()->regulasi_id);
                $this->assertNull($renstra->fresh()->regulasi_id);
            }
            $this->assertTrue(Berkas::withTrashed()->findOrFail($file->id)->trashed());
        } else {
            $this->assertTrue($targetResult['changed']);
            $this->assertSame(['outcome' => 'validation', 'fields' => [$attachment ? 'berkas' : 'regulasi'], 'status' => 422], $deleteResult);
            $this->assertSame('74.200000000000', TargetKinerja::sole()->baseline);
            $this->assertSame('76.250000000000', TargetKinerja::sole()->target_tahunan);
            $this->assertSame($regulasi->id, $indicator->fresh()->regulasi_id);
            $this->assertSame($regulasi->id, $renstra->fresh()->regulasi_id);
            $this->assertFalse(Berkas::withTrashed()->findOrFail($file->id)->trashed());
        }
        $this->assertSame($operation === 'save' ? 1 : 0, AuditLog::where('tindakan', 'target_tahunan.simpan')->where('actor_id', $reader->id)->count());
        $this->assertSame($operation === 'show' ? 1 : 0, AuditLog::where('tindakan', $deleteAction)->where('objek_id', $objectId)->where('actor_id', $deleter->id)->count());
        $denial = AuditLog::where('tindakan', $deleteAction.'_ditolak')->where('objek_id', $objectId)->where('actor_id', $deleter->id)->get();
        $this->assertCount($operation === 'save' ? 1 : 0, $denial);
        if ($operation === 'save') {
            $this->assertSame(1, $denial->sole()->nilai_baru['jumlah_indikator_aktif']);
            $this->assertSame(0, $denial->sole()->nilai_baru['jumlah_renstra_aktif']);
        }
    }

    public static function regulasiOperations(): array
    {
        return [['show', false], ['save', false], ['show', true], ['save', true]];
    }

    #[DataProvider('authorizationChanges')]
    public function test_save_vs_authorization_change_rechecks_both_permissions(string $permission): void
    {
        [$first, $second, $indicator, $data] = $this->runtimeFixture();
        DB::beginTransaction();
        app(CreateDeny::class)->handle($first, $second->id, Permission::where('kode', $permission)->sole()->id, null, 'Pencabutan fixture');
        $worker = $this->start(['operation' => 'save', 'actor_id' => $second->id, 'indicator_id' => $indicator->id, 'year' => 2026, 'data' => $data]);
        $this->go($worker);
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::commit();
        $this->assertSame(['outcome' => 'denied'], $this->workerResult($worker));
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->count());
    }

    public static function authorizationChanges(): array
    {
        return [['indikator:read'], ['target:update']];
    }

    private function go(array $worker): void
    {
        $worker['input']->write("GO\n");
    }

    private function blockedBy(array $worker, int $blocker): void
    {
        $this->until(function () use ($worker, $blocker): bool {
            DB::select('select pg_stat_clear_snapshot()');

            return (bool) DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$blocker, $worker['pid']])->blocked;
        }, [$worker]);
        $this->assertNotSame($blocker, $worker['pid']);
    }

    private function workerResult(array $worker): array
    {
        $this->until(fn () => str_contains($worker['process']->getOutput(), 'RESULT:'), [$worker]);
        $worker['input']->close();
        $worker['process']->wait();
        $this->assertSame(0, $worker['process']->getExitCode(), $worker['process']->getErrorOutput());
        preg_match('/RESULT:(.+)/', $worker['process']->getOutput(), $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function until(callable $ready, array $workers): void
    {
        $deadline = microtime(true) + 20;
        do {
            if ($ready()) {
                return;
            }
            foreach ($workers as $worker) {
                if (! $worker['process']->isRunning()) {
                    $this->fail('Worker berhenti sebelum barrier: '.$worker['process']->getOutput().$worker['process']->getErrorOutput());
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Barrier PostgreSQL tidak tercapai.');
    }
}
