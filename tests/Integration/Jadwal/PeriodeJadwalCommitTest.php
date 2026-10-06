<?php

namespace Tests\Integration\Jadwal;

use App\Models\Permission;
use App\Models\UserPermissionDeny;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;
use Tests\Support\JadwalFixtures;
use Tests\TestCase;

class PeriodeJadwalCommitTest extends TestCase
{
    use DatabaseMigrations, JadwalFixtures;

    /** Tanpa outer transaction; cleanup disposable memakai fresh karena audit append-only. */
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

    public function test_response_failure_after_action_keeps_physically_committed_calendar_and_audit(): void
    {
        $actor = $this->calendarActor();
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        Inertia::partialMock()->shouldReceive('flash')->once()->andReturnUsing(function (): never {
            $this->assertSame(0, DB::transactionLevel());
            throw new RuntimeException('Response unavailable');
        });

        $this->actingAs($actor)->post('/jadwal', $data)->assertStatus(500);

        $this->assertCommittedCalendar($this->commitObserver(), $data, $actor->id);
    }

    public function test_calendar_commit_survives_forbidden_redirect_get_after_permission_revocation(): void
    {
        $actor = $this->calendarActor(['jadwal:create']);
        $data = $this->calendarPayload($this->calendarRenstra($actor), $this->calendarMaster());
        $response = $this->actingAs($actor)->post('/jadwal', $data)->assertRedirect();
        $observer = $this->commitObserver();
        $this->assertSame(1, $observer->table('jadwal_tahunan')->count());

        // Perubahan izin hanya pada fixture QA, di antara respons mutasi dan GET redirect.
        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'jadwal:create')->sole()->id,
            'ditetapkan_oleh' => $actor->id, 'alasan' => 'Fixture izin dicabut setelah commit']);
        $this->get($response->headers->get('Location'))->assertForbidden();

        $this->assertSame('aktif', $actor->fresh()->status);
        $this->assertCommittedCalendar($observer, $data, $actor->id);
    }

    public function test_master_commit_survives_forbidden_redirect_get_after_permission_revocation(): void
    {
        $actor = $this->calendarActor(['periode:update']);
        $periode = $this->calendarMaster();
        $response = $this->actingAs($actor)->put('/periode/'.$periode->id, [
            'nama' => 'Final diperbarui', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true, 'revisi' => 1,
        ])->assertRedirect('/periode');
        $observer = $this->commitObserver();
        $this->assertSame('Final diperbarui', $observer->table('periode')->where('id', $periode->id)->value('nama'));

        UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'periode:update')->sole()->id,
            'ditetapkan_oleh' => $actor->id, 'alasan' => 'Fixture izin dicabut setelah commit']);
        $this->get($response->headers->get('Location'))->assertForbidden();

        $this->assertSame('aktif', $actor->fresh()->status);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('Final diperbarui', $observer->table('periode')->where('id', $periode->id)->value('nama'));
        $this->assertSame(2, $observer->table('periode')->where('id', $periode->id)->value('revisi'));
        $audit = $observer->table('audit_log')->where('tindakan', 'periode.ubah')->sole();
        $this->assertSame($periode->id, $audit->objek_id);
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame('Final diperbarui', json_decode($audit->nilai_baru, true, flags: JSON_THROW_ON_ERROR)['nama']);
        $this->assertSame(0, $observer->table('audit_log')->where('tindakan', 'periode.ubah_ditolak')->count());
    }

    private function commitObserver(): Connection
    {
        $writer = DB::connection();
        $this->assertSame(0, $writer->transactionLevel());
        config(['database.connections.commit_observer' => $writer->getConfig()]);
        $observer = DB::connection('commit_observer');
        $this->assertSame(0, $observer->transactionLevel());
        $this->assertNotSame($writer->selectOne('select pg_backend_pid() as pid')->pid, $observer->selectOne('select pg_backend_pid() as pid')->pid);
        $this->assertSame('sakip_test', $observer->selectOne('select current_database() as database')->database);

        return $observer;
    }

    private function assertCommittedCalendar(Connection $observer, array $data, string $actorId): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $jadwal = $observer->table('jadwal_tahunan')->sole();
        $this->assertSame($data['renstra_id'], $jadwal->renstra_id);
        $this->assertSame($data['penutupan'], $jadwal->penutupan);
        $this->assertSame('draft', $jadwal->status);
        $window = $observer->table('jadwal_periode')->sole();
        $this->assertSame($jadwal->id, $window->jadwal_id);
        foreach (array_diff_key($data['periode'][0], ['periode_revisi' => true]) as $field => $value) {
            $this->assertSame($value, $window->$field);
        }
        $audit = $observer->table('audit_log')->where('tindakan', 'jadwal.tambah')->sole();
        $this->assertSame($jadwal->id, $audit->objek_id);
        $this->assertSame($actorId, $audit->actor_id);
        $this->assertSame($data['penutupan'], json_decode($audit->nilai_baru, true, flags: JSON_THROW_ON_ERROR)['penutupan']);
        $this->assertSame(0, $observer->table('audit_log')->where('tindakan', 'jadwal.tambah_ditolak')->count());
    }
}
