<?php

namespace Tests\Integration\Jadwal;

use App\Actions\Access\CreateDeny;
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
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Models\AuditLog;
use App\Models\Berkas;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Permission;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Services\Storage\StoragePolicyDefaults;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/**
 * Race aktivasi vs writer produksi pada PostgreSQL dua koneksi: proses test memegang lock writer pertama di
 * transaksi terbuka, worker terpisah menjalankan lawannya dan dibuktikan terblokir sebelum commit.
 */
class JadwalActivationConcurrencyTest extends TestCase
{
    use DatabaseMigrations, JadwalActivationFixtures;

    private const NOW = '2026-10-07T02:00:00Z';

    private array $workers = [];

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse(self::NOW));
    }

    public function test_target_written_first_is_frozen_with_new_value(): void
    {
        $this->readyJadwal();
        $indikator = IndikatorKinerja::sole();
        $writer = $this->activator(['indikator:read', 'target:update']);
        $state = app(ShowTargetTahunan::class)->handle($writer, $indikator->id, 2026);
        $job = $this->activationJob();
        DB::beginTransaction();
        app(SaveTargetTahunan::class)->handle($writer, $indikator->id, 2026, ['baseline' => null, 'target_tahunan' => '90', 'expected_state' => $state['expected_state'], 'operation_id' => (string) Str::uuid()]);
        $result = $this->raceAgainstOpenTransaction($job);
        $this->assertSame(['outcome' => 'changed', 'changed' => true], $result);
        $this->assertSame('90.000000000000', JadwalSnapshot::sole()->getRawOriginal('target'));
    }

    public function test_waiting_target_writer_sees_snapshot_and_correction_needs_metadata(): void
    {
        $this->readyJadwal();
        $indikator = IndikatorKinerja::sole();
        $writer = $this->activator(['indikator:read', 'target:update']);
        $stale = app(ShowTargetTahunan::class)->handle($writer, $indikator->id, 2026)['expected_state'];
        $data = ['baseline' => null, 'target_tahunan' => '90', 'operation_id' => (string) Str::uuid()];
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'save_target', 'actor_id' => $writer->id, 'id' => $indikator->id, 'year' => 2026, 'data' => [...$data, 'expected_state' => $stale]]);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['expected_state']], $result);
        $before = $this->snapshotFingerprint();

        $fresh = app(ShowTargetTahunan::class)->handle($writer, $indikator->id, 2026)['expected_state'];
        try {
            app(SaveTargetTahunan::class)->handle($writer, $indikator->id, 2026, [...$data, 'expected_state' => $fresh]);
            $this->fail('Koreksi setelah snapshot wajib alasan dan rujukan.');
        } catch (ValidationException $exception) {
            $this->assertEqualsCanonicalizing(['alasan', 'rujukan_sumber'], array_keys($exception->errors()));
        }
        app(SaveTargetTahunan::class)->handle($writer, $indikator->id, 2026, [...$data, 'expected_state' => $fresh, 'alasan' => 'Koreksi PK', 'rujukan_sumber' => 'Nota fixture']);
        $this->assertSame('90.000000000000', DB::table('target_kinerjas')->where('indikator_kinerja_id', $indikator->id)->value('target_tahunan'));
        $this->assertSame($before, $this->snapshotFingerprint());
    }

    public function test_absent_target_created_first_is_waited_for_not_frozen_as_null(): void
    {
        $this->readyJadwal(withIndicator: false);
        $indikator = $this->eligibleIndicator(withTarget: false);
        $writer = $this->activator(['indikator:read', 'target:update']);
        $state = app(ShowTargetTahunan::class)->handle($writer, $indikator->id, 2026);
        $job = $this->activationJob();
        DB::beginTransaction();
        app(SaveTargetTahunan::class)->handle($writer, $indikator->id, 2026, ['baseline' => null, 'target_tahunan' => '5', 'expected_state' => $state['expected_state'], 'operation_id' => (string) Str::uuid()]);
        $this->assertSame(['outcome' => 'changed', 'changed' => true], $this->raceAgainstOpenTransaction($job));
        $this->assertSame('5.000000000000', JadwalSnapshot::sole()->getRawOriginal('target'));
    }

    public function test_concurrent_activations_have_one_transition_and_one_snapshot_set(): void
    {
        $this->readyJadwal();
        $job = $this->activationJob();
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $this->assertSame(['outcome' => 'changed', 'changed' => false], $this->raceAgainstOpenTransaction($job));
        $this->assertSame(1, JadwalSnapshot::count());
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.aktivasi')->count());
        $this->assertSame(2, JadwalTahunan::sole()->revisi);
    }

    /** @return array<string, array{string, bool}> */
    public static function indicatorWriters(): array
    {
        return ['formula dulu' => ['formula', true], 'aktivasi dulu vs formula' => ['formula', false], 'arsip dulu' => ['archive', true],
            'aktivasi dulu vs arsip' => ['archive', false], 'pindah unit dulu' => ['unit', true], 'aktivasi dulu vs pindah unit' => ['unit', false]];
    }

    #[DataProvider('indicatorWriters')]
    public function test_indicator_writers_and_activation_freeze_one_locked_state(string $operation, bool $writerFirst): void
    {
        $this->readyJadwal();
        $indikator = IndikatorKinerja::sole();
        $writer = $this->activator(['indikator:read', 'indikator:update', 'indikator:delete']);
        $unit = Unit::create(['nama' => 'Unit baru '.Str::random(6), 'status' => 'aktif', 'created_by' => $writer->id]);
        $writerJob = ['operation' => $operation, 'actor_id' => $writer->id, 'id' => $indikator->id, 'data' => match ($operation) {
            'formula' => ['definisi_operasional' => 'Definisi baru', 'expected_updated_at' => $indikator->updated_at->toISOString(), 'return_to' => 'sasaran-indikator'],
            'unit' => ['unit_id' => $unit->id, 'alasan' => 'Pindah unit fixture'],
            default => [],
        }];
        $activationJob = $this->activationJob();
        DB::beginTransaction();
        if ($writerFirst) {
            match ($operation) {
                'formula' => app(ChangeIndicatorFormula::class)->handle($writer, $indikator, $writerJob['data']),
                'unit' => app(PindahUnitIndikator::class)->handle($writer, $indikator, $writerJob['data']),
                'archive' => app(DestroyIndikator::class)->handle($writer, $indikator, 'Arsip fixture konkurensi'),
            };
            $result = $this->raceAgainstOpenTransaction($activationJob);
        } else {
            app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
            $result = $this->raceAgainstOpenTransaction($writerJob);
            $this->assertNotSame('error', $result['outcome'], json_encode($result));
        }
        $current = $indikator->fresh();
        if ($writerFirst && $operation === 'archive') {
            $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $result);
            $this->assertSame(0, JadwalSnapshot::count());
            $this->assertSame('draft', JadwalTahunan::sole()->status);

            return;
        }
        $snapshot = JadwalSnapshot::sole();
        if ($writerFirst) {
            $this->assertSame(['outcome' => 'changed', 'changed' => true], $result);
            $this->assertSame([$current->definisi_operasional, $current->unit_id], [$snapshot->definisi, $snapshot->unit_id]);
        } else {
            $this->assertSame(['Definisi operasional fixture', $this->ready['unit']->id], [$snapshot->definisi, $snapshot->unit_id]);
        }
    }

    public function test_indicator_inserted_during_enumeration_rolls_back_with_conflict(): void
    {
        $this->readyJadwal();
        // Izin indikator:create harus berasal dari role agar created_by_role dapat ditentukan.
        $writer = $this->calendarActor(['indikator:read', 'indikator:create', 'komponen:read']);
        $worker = $this->start([...$this->activationJob(), 'pause_on' => ['from "renstras"', 'for update']]);
        $this->go($worker);
        $this->until(fn () => str_contains($worker['process']->getOutput(), 'PAUSED'), [$worker]);
        $dibuat = app(StoreIndikator::class)->handle($writer, $this->newIndicatorPayload());
        // Indikator baru lengkap, sehingga hanya re-enumerasi sesudah lock Renstra yang menolak snapshot campuran.
        // Kode indikator dibangkitkan server, jadi identitas diambil dari hasil Action, bukan dari payload.
        TargetKinerja::create(['indikator_kinerja_id' => $dibuat['indikator']->id, 'tahun' => 2026, 'target_tahunan' => '3']);
        $worker['input']->write("CONTINUE\n");
        $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $this->workerResult($worker));
        $this->assertSame(0, JadwalSnapshot::count());
        $this->assertSame('draft', JadwalTahunan::sole()->status);
        $this->assertSame(1, AuditLog::where('tindakan', 'jadwal.aktivasi_ditolak')->count());
    }

    public function test_indicator_inserted_after_activation_lock_waits_and_stays_downstream(): void
    {
        $this->readyJadwal();
        // Izin indikator:create harus berasal dari role agar created_by_role dapat ditentukan.
        $writer = $this->calendarActor(['indikator:read', 'indikator:create', 'komponen:read']);
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'store_indikator', 'actor_id' => $writer->id, 'data' => $this->newIndicatorPayload()]);
        $this->assertSame('changed', $result['outcome'], json_encode($result));
        $this->assertSame(2, IndikatorKinerja::count());
        $this->assertSame(1, JadwalSnapshot::count());
    }

    public function test_last_attachment_deletion_and_activation_serialize_on_pk(): void
    {
        $this->readyJadwal();
        $deleter = $this->activator(['pk:update', 'berkas:delete']);
        $berkas = Berkas::sole();
        $job = $this->activationJob();
        DB::beginTransaction();
        app(DeleteBerkasPerjanjianKinerja::class)->handle($this->ready['pk'], $berkas, 'Hapus lampiran fixture', $deleter);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $this->raceAgainstOpenTransaction($job));
        $this->assertSame('draft', JadwalTahunan::sole()->status);

        $this->pkAttachment();
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'delete_berkas', 'actor_id' => $deleter->id, 'id' => $this->ready['pk']->id, 'berkas_id' => Berkas::latest('created_at')->first()->id]);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['berkas']], $result);
        $this->assertSame('aktif', JadwalTahunan::sole()->status);
        $this->assertSame(1, Berkas::count());
    }

    public function test_calendar_identity_change_and_draft_edit_after_activation_are_rejected(): void
    {
        $this->readyJadwal();
        $editor = $this->activator(['jadwal:update']);
        $job = $this->activationJob(expectedRevisi: 1);
        DB::beginTransaction();
        app(SaveJadwalDraft::class)->handle($editor, [...$this->calendarPayload($this->ready['renstra'], $this->ready['periode'], 2027), 'revisi' => 1], $this->ready['jadwal']);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $this->raceAgainstOpenTransaction($job));
        $this->assertSame(['draft', 2027], [JadwalTahunan::sole()->status, JadwalTahunan::sole()->tahun]);

        $this->ready['pk']->update(['tahun' => 2027]);
        TargetKinerja::create(['indikator_kinerja_id' => IndikatorKinerja::sole()->id, 'tahun' => 2027, 'target_tahunan' => '80']);
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'save_jadwal', 'actor_id' => $editor->id, 'id' => $this->ready['jadwal']->id,
            'data' => [...$this->calendarPayload($this->ready['renstra'], $this->ready['periode'], 2027), 'penutupan' => '2028-01-20', 'revisi' => 3]]);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['jadwal']], $result);
        $this->assertSame(['aktif', '2028-01-19'], [JadwalTahunan::sole()->status, JadwalTahunan::sole()->penutupan->format('Y-m-d')]);
    }

    public function test_period_deactivation_first_blocks_activation_and_waits_otherwise(): void
    {
        $this->readyJadwal();
        $manager = $this->activator(['periode:update']);
        DB::table('periode')->update(['is_nilai_akhir' => false]);
        $this->calendarMaster(5);
        $periode = ['nama' => $this->ready['periode']->nama, 'urutan' => 4, 'aktif' => false, 'is_nilai_akhir' => false, 'revisi' => 1];
        $job = $this->activationJob();
        DB::beginTransaction();
        app(SavePeriode::class)->handle($manager, $periode, $this->ready['periode']);
        $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $this->raceAgainstOpenTransaction($job));
        $this->assertSame(0, JadwalSnapshot::count());

        DB::table('periode')->where('id', $this->ready['periode']->id)->update(['aktif' => true]);
        $revisi = DB::table('periode')->where('id', $this->ready['periode']->id)->value('revisi');
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'save_periode', 'actor_id' => $manager->id, 'id' => $this->ready['periode']->id,
            'data' => [...$periode, 'revisi' => $revisi]]);
        $this->assertNotSame('error', $result['outcome'], json_encode($result));
        $this->assertSame([$this->ready['periode']->id], JadwalSnapshot::pluck('periode_mulai_id')->all());
    }

    public function test_upload_toggle_off_first_lets_activation_reuse_its_marker(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        app(StoragePolicyDefaults::class)->ensure();
        $admin = $this->activator(['pengaturan:update']);
        $job = $this->activationJob();
        DB::beginTransaction();
        app(UpdateStoragePolicyAction::class)->handle($admin, $this->storagePayload(false, 1));
        $this->assertSame(['outcome' => 'changed', 'changed' => true], $this->raceAgainstOpenTransaction($job));
        $this->assertSame(1, $this->markers('berkas.tandai_tidak_dapat_dipenuhi'));
        $this->assertTrue(AuditLog::where('tindakan', 'jadwal.aktivasi')->sole()->nilai_baru['pengecualian_lampiran_pk']);
    }

    public function test_upload_toggle_on_waits_for_activation_marker_then_revokes_it(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        app(StoragePolicyDefaults::class)->ensure();
        DB::table('pengaturan')->where('kunci', 'berkas.unggahan_aktif')->update(['nilai' => 'false']);
        $admin = $this->activator(['pengaturan:update']);
        DB::beginTransaction();
        app(ActivateJadwal::class)->handle($this->activator(), $this->ready['jadwal']->id, $this->activationPayload());
        $result = $this->raceAgainstOpenTransaction(['operation' => 'storage', 'actor_id' => $admin->id, 'data' => $this->storagePayload(true, 1)]);
        $this->assertSame(['outcome' => 'changed', 'changed' => true], $result);
        $this->assertSame([1, 1], [$this->markers('berkas.tandai_tidak_dapat_dipenuhi'), $this->markers('berkas.cabut_tidak_dapat_dipenuhi')]);
        $this->assertSame('aktif', JadwalTahunan::sole()->status);
    }

    public function test_upload_setting_created_after_absent_lock_is_not_used_for_exception(): void
    {
        $this->readyJadwal();
        Berkas::query()->delete();
        $this->uploadSetting(null);
        $admin = $this->activator(['pengaturan:update']);
        // Aktivasi sudah melewati lock setting (row absent, tidak ada yang terkunci) lalu berhenti sebelum membaca konteks.
        $worker = $this->start([...$this->activationJob(), 'pause_on' => ['from "target_kinerjas"', 'for update']]);
        $this->go($worker);
        $this->until(fn () => str_contains($worker['process']->getOutput(), 'PAUSED'), [$worker]);
        app(UpdateStoragePolicyAction::class)->handle($admin, $this->storagePayload(false, 1));
        $worker['input']->write("CONTINUE\n");
        $this->assertSame(['outcome' => 'validation', 'fields' => ['aktivasi']], $this->workerResult($worker));
        $this->assertSame('draft', JadwalTahunan::sole()->status);
        $this->assertSame(0, JadwalSnapshot::count());
    }

    public function test_deny_committed_first_is_seen_by_live_recheck(): void
    {
        $this->readyJadwal();
        $actor = $this->activator();
        $manager = $this->activator(['akses:update']);
        $job = $this->activationJob(actor: $actor);
        DB::beginTransaction();
        app(CreateDeny::class)->handle($manager, $actor->id, Permission::where('kode', 'jadwal:aktivasi')->value('id'), null, 'Pencabutan fixture');
        $this->assertSame(['outcome' => 'denied'], $this->raceAgainstOpenTransaction($job));
        $this->assertSame('draft', JadwalTahunan::sole()->status);
        $this->assertSame('ditolak', AuditLog::where('tindakan', 'jadwal.aktivasi_ditolak')->sole()->dasar_izin['keputusan']);
    }

    private function activationJob(?User $actor = null, ?int $expectedRevisi = null): array
    {
        return ['operation' => 'activate', 'actor_id' => ($actor ?? $this->activator())->id, 'id' => $this->ready['jadwal']->id,
            'data' => $this->activationPayload($expectedRevisi ? ['expected_revisi' => $expectedRevisi] : [])];
    }

    private function newIndicatorPayload(): array
    {
        // Tanpa `kode`: dibangkitkan server-side oleh StoreIndikator (kode berurutan `IK-<nomor>`).
        return ['sasaran_strategis_id' => $this->ready['sasaran']->id, 'nama' => 'Indikator baru', 'satuan' => 'persen',
            'unit_id' => $this->ready['unit']->id, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'presisi' => 2, 'desimal_tampilan' => 2];
    }

    private function storagePayload(bool $upload, int $version): array
    {
        return ['berkas_unggahan_aktif' => $upload, 'berkas_ukuran_maks_kb' => 10240, 'berkas_format_diizinkan' => 'pdf,docx,xlsx,jpg,jpeg,png',
            'berkas_tautan_selalu_diizinkan' => true, 'expected_updated_at' => '', 'expected_version' => $version, 'alasan' => 'Toggle fixture konkurensi'];
    }

    private function markers(string $tindakan): int
    {
        return AuditLog::where('tindakan', $tindakan)->where('objek_id', $this->ready['pk']->id)->count();
    }

    /** Worker harus benar-benar menunggu transaksi proses test sebelum commit; hasil dibaca sesudah commit. */
    private function raceAgainstOpenTransaction(array $job): array
    {
        $this->assertSame(1, DB::transactionLevel());
        $worker = $this->start($job);
        $this->go($worker);
        $this->until(function () use ($worker): bool {
            DB::select('select pg_stat_clear_snapshot()');

            return (bool) DB::selectOne('select pg_backend_pid() = any(pg_blocking_pids(?)) as blocked', [$worker['pid']])->blocked;
        }, [$worker]);
        DB::commit();

        return $this->workerResult($worker);
    }

    private function start(array $payload): array
    {
        $c = DB::connection();
        $env = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_HOST' => (string) $c->getConfig('host'),
            'DB_PORT' => (string) $c->getConfig('port'), 'DB_DATABASE' => 'sakip_test', 'DB_USERNAME' => 'sakip_test', 'DB_PASSWORD' => (string) $c->getConfig('password'),
            'SAKIP_TEST_ALLOW_DATABASE_RESET' => '1', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
        $input = new InputStream;
        $process = new Process([PHP_BINARY, '-d', 'variables_order=EGPCS', base_path('tests/Support/jadwal-activation-worker.php'),
            json_encode([...$payload, 'now' => self::NOW], JSON_THROW_ON_ERROR)], base_path(), $env, $input, 40);
        $process->start();
        $worker = ['process' => $process, 'input' => $input];
        $this->workers[] = $worker;
        $this->until(fn () => preg_match('/READY:(\d+)/', $process->getOutput()) === 1, [$worker]);
        preg_match('/READY:(\d+)/', $process->getOutput(), $match);

        return [...$worker, 'pid' => (int) $match[1]];
    }

    private function go(array $worker): void
    {
        $worker['input']->write("GO\n");
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
        $deadline = microtime(true) + 25;
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
        throw new RuntimeException('Barrier PostgreSQL tidak tercapai.');
    }
}
