<?php

namespace Tests\Integration\Kegiatan;

use App\Actions\Access\RevokeUnitGrantAction;
use App\Models\AuditLog;
use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class KegiatanConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

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
            // Audit append-only: rebuild hanya database disposable sesudah worker dihentikan.
            $this->assertDisposableDatabase($this->app);
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            RefreshDatabaseState::$migrated = false;
        });
    }

    #[DataProvider('operations')]
    public function test_izin_yang_dicabut_selama_menunggu_lock_diperiksa_ulang(string $operation): void
    {
        [$admin, $actor, $data] = $this->fixture();
        $kegiatan = $operation === 'update' ? Kegiatan::create($data + ['created_by' => $actor->id])->fresh() : null;
        if ($kegiatan) {
            $data['expected_state'] = $kegiatan->versionToken();
        }
        DB::beginTransaction();
        User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $worker = $this->start(['actor_id' => $actor->id, 'data' => $data, 'id' => $kegiatan?->id]);
        $worker['input']->write("GO\n");
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        $grant = UserPermissionGrant::where('user_id', $actor->id)->where('permission_id', Permission::where('kode', 'kegiatan:'.$operation)->value('id'))->sole();
        app(RevokeUnitGrantAction::class)->handle($admin, $grant->id, 'Pencabutan izin saat kegiatan menunggu lock.');
        DB::commit();
        $this->assertSame(['outcome' => 'denied'], $this->workerResult($worker));
        $this->assertSame($kegiatan ? 1 : 0, Kegiatan::count());
        $this->assertSame(0, AuditLog::where('tindakan', $kegiatan ? 'kegiatan.ubah' : 'kegiatan.buat')->count());
        $this->assertSame(1, AuditLog::where('tindakan', $kegiatan ? 'kegiatan.ubah_ditolak' : 'kegiatan.buat_ditolak')->count());
    }

    public static function operations(): array
    {
        return [['create'], ['update']];
    }

    public function test_dua_simpan_dari_form_yang_sama_tidak_menimpa_perubahan(): void
    {
        [$admin, $actor, $data] = $this->fixture();
        $kegiatan = Kegiatan::create($data + ['created_by' => $actor->id])->fresh();
        $data['expected_state'] = $kegiatan->versionToken();
        DB::beginTransaction();
        Kegiatan::whereKey($kegiatan->id)->lockForUpdate()->firstOrFail();
        $first = $this->start(['actor_id' => $actor->id, 'id' => $kegiatan->id, 'data' => array_replace($data, ['nama' => 'Perubahan pertama'])]);
        $second = $this->start(['actor_id' => $admin->id, 'id' => $kegiatan->id, 'data' => array_replace($data, ['nama' => 'Perubahan kedua'])]);
        $first['input']->write("GO\n");
        $pid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
        $this->blockedBy($first, $pid);
        $second['input']->write("GO\n");
        // Antrean tuple PostgreSQL dapat menunggu waiter pertama, bukan langsung holder parent.
        $this->blockedBy($second, $first['pid']);
        DB::commit();
        $outcomes = [$this->workerResult($first), $this->workerResult($second)];
        $this->assertEqualsCanonicalizing([['outcome' => 'saved'], ['outcome' => 'validation', 'fields' => ['expected_state']]], $outcomes);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah')->count());
    }

    private function fixture(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $admin = $this->actor('superadmin');
        $actor = $this->actor('pegawai');
        $unit = Unit::create(['nama' => 'Unit race', 'status' => 'aktif', 'created_by' => $admin->id]);
        $periode = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $unit->id, 'alasan' => 'Fixture race', 'diberikan_oleh' => $admin->id]);
        }

        return [$admin, $actor, ['unit_id' => $unit->id, 'tahun' => 2026, 'periode_id' => $periode->id, 'nama' => 'Rencana awal', 'tujuan' => 'Tujuan race', 'sasaran_peserta' => null, 'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null]];
    }

    public function test_simpan_tidak_menahan_konfigurasi_periode_saat_menunggu_aktor_writer_periode(): void
    {
        [, $actor, $data] = $this->fixture();
        DB::beginTransaction();
        // Writer periode mengunci aktor bersama lebih dahulu, baru konfigurasi eksklusif.
        User::whereKey($actor->id)->sharedLock()->firstOrFail();
        $worker = $this->start(['actor_id' => $actor->id, 'data' => $data]);
        $worker['input']->write("GO\n");
        $this->blockedBy($worker, (int) DB::selectOne('select pg_backend_pid() as pid')->pid);
        DB::statement("SET LOCAL lock_timeout = '500ms'");
        Periode::lockConfiguration(exclusive: true);
        DB::commit();
        $this->assertSame(['outcome' => 'saved'], $this->workerResult($worker));
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat')->count());
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    private function start(array $payload): array
    {
        $input = new InputStream;
        // Dua boot PHP di bind mount Windows dapat melewati 30 detik; batas tunggu SQL tetap ketat.
        $process = new Process([PHP_BINARY, base_path('tests/Support/kegiatan-worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), timeout: 60);
        $process->setInput($input)->start();
        $this->workers[] = ['process' => $process, 'input' => $input];
        $this->until(fn () => str_contains($process->getOutput(), 'READY:'));
        preg_match('/READY:(\d+)/', $process->getOutput(), $match);

        return ['process' => $process, 'input' => $input, 'pid' => (int) $match[1]];
    }

    private function blockedBy(array $worker, int $blocker): void
    {
        $this->until(function () use ($worker, $blocker): bool {
            DB::select('select pg_stat_clear_snapshot()');

            return (bool) DB::selectOne('select ? = any(pg_blocking_pids(?)) as blocked', [$blocker, $worker['pid']])->blocked;
        });
        $this->assertNotSame($blocker, $worker['pid']);
    }

    private function workerResult(array $worker): array
    {
        $this->until(fn () => str_contains($worker['process']->getOutput(), 'RESULT:'));
        $worker['input']->close();
        $worker['process']->wait();
        $this->assertSame(0, $worker['process']->getExitCode(), $worker['process']->getErrorOutput());
        preg_match('/RESULT:(.+)/', $worker['process']->getOutput(), $match);

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    private function until(callable $ready): void
    {
        $deadline = microtime(true) + 20;
        do {
            if ($ready()) {
                return;
            }
            foreach ($this->workers as $worker) {
                if (! $worker['process']->isRunning() && ! str_contains($worker['process']->getOutput(), 'RESULT:')) {
                    $this->fail('Worker berhenti sebelum barrier: '.$worker['process']->getOutput().$worker['process']->getErrorOutput());
                }
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Barrier PostgreSQL tidak tercapai.');
    }
}
