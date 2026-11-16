<?php

namespace Tests\Integration\Jadwal;

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;
use Tests\Support\JadwalActivationFixtures;
use Tests\TestCase;

/** Bukti commit fisik dibaca koneksi lain; kegagalan sesudah Action tidak boleh tampak sebagai rollback. */
class JadwalActivationCommitTest extends TestCase
{
    use DatabaseMigrations, JadwalActivationFixtures;

    public function runDatabaseMigrations(): void
    {
        $this->beforeRefreshingDatabase();
        $this->refreshTestDatabase();
        $this->afterRefreshingDatabase();
        $this->beforeApplicationDestroyed(function (): void {
            try {
                DB::purge('commit_observer');
                if (DB::transactionLevel() !== 0) {
                    throw new RuntimeException('Transaksi masih aktif sebelum cleanup.');
                }
                $this->assertDisposableDatabase($this->app);
                $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            } finally {
                RefreshDatabaseState::$migrated = false;
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00', 'Asia/Makassar'));
    }

    public function test_flash_failure_after_action_keeps_committed_activation(): void
    {
        $this->readyWithComponents();
        $actor = $this->activator();
        Inertia::partialMock()->shouldReceive('flash')->once()->andReturnUsing(function (): never {
            $this->assertSame(0, DB::transactionLevel());
            throw new RuntimeException('Response unavailable');
        });

        $this->actingAs($actor)->post('/jadwal/'.$this->ready['jadwal']->id.'/aktivasi', $this->activationPayload())->assertStatus(500);

        $this->assertCommittedActivation($this->commitObserver(), $actor->id);
    }

    public function test_activation_survives_forbidden_redirect_get_after_permission_revocation(): void
    {
        $this->readyWithComponents();
        $actor = $this->activator();
        $response = $this->actingAs($actor)->post('/jadwal/'.$this->ready['jadwal']->id.'/aktivasi', $this->activationPayload())->assertRedirect();
        $observer = $this->commitObserver();
        $this->assertSame('aktif', $observer->table('jadwal_tahunan')->value('status'));

        $this->denyPermission($actor, 'jadwal:aktivasi');
        $this->get($response->headers->get('Location'))->assertForbidden();

        $this->assertCommittedActivation($observer, $actor->id);
    }

    public function test_readiness_outside_caller_transaction_uses_read_only_repeatable_read(): void
    {
        $this->readyJadwal();
        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = $query->sql;
        });
        $this->actingAs($this->activator())->getJson('/jadwal/'.$this->ready['jadwal']->id.'/kesiapan-aktivasi')->assertOk();
        $this->assertContains('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY', $statements);
        // Statement isolasi harus menjadi query pertama di transaksi readiness, sebelum lookup Jadwal.
        $set = array_search('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY', $statements, true);
        $this->assertStringContainsString('"jadwal_tahunan"', $statements[$set + 1]);
    }

    /** Indikator nonmanual agar child snapshot ikut dibuktikan oleh koneksi observer. */
    private function readyWithComponents(): void
    {
        $this->readyJadwal(withIndicator: false);
        $this->eligibleIndicator(target: '76.25', tipe: 'rasio_persen', komponen: [['kode' => 'A', 'peran' => 'pembilang'], ['kode' => 'B', 'peran' => 'penyebut', 'bobot' => '2.5']]);
    }

    private function commitObserver(): Connection
    {
        $writer = DB::connection();
        $this->assertSame(0, $writer->transactionLevel());
        config(['database.connections.commit_observer' => $writer->getConfig()]);
        $observer = DB::connection('commit_observer');
        $this->assertNotSame($writer->selectOne('select pg_backend_pid() as pid')->pid, $observer->selectOne('select pg_backend_pid() as pid')->pid);
        $this->assertSame('sakip_test', $observer->selectOne('select current_database() as database')->database);

        return $observer;
    }

    private function assertCommittedActivation(Connection $observer, string $actorId): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $jadwal = $observer->table('jadwal_tahunan')->sole();
        $this->assertSame(['aktif', $this->ready['pk']->id, 2], [$jadwal->status, $jadwal->renstra_pk_id, $jadwal->revisi]);
        $this->assertNotNull($jadwal->activated_at);
        $snapshot = $observer->table('jadwal_snapshot')->sole();
        $this->assertSame('76.250000000000', $snapshot->target);
        $children = $observer->table('jadwal_snapshot_komponen')->where('jadwal_snapshot_id', $snapshot->id)->orderBy('urutan')->get(['kode', 'peran', 'bobot', 'urutan']);
        $this->assertSame([['A', 'pembilang', '1.000000000000', 1], ['B', 'penyebut', '2.500000000000', 2]],
            $children->map(fn (object $row): array => [$row->kode, $row->peran, $row->bobot, $row->urutan])->all());
        $audit = $observer->table('audit_log')->where('tindakan', 'jadwal.aktivasi')->sole();
        $this->assertSame([$jadwal->id, $actorId], [$audit->objek_id, $audit->actor_id]);
        $this->assertSame([$snapshot->id], json_decode($audit->nilai_baru, true, flags: JSON_THROW_ON_ERROR)['snapshot_ids']);
        $this->assertSame(1, $observer->table('audit_log')->where('tindakan', 'jadwal_snapshot.buat')->count());
        $this->assertSame(0, $observer->table('audit_log')->where('tindakan', 'jadwal.aktivasi_ditolak')->count());
    }
}
