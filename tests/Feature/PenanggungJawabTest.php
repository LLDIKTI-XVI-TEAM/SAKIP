<?php

namespace Tests\Feature;

use App\Actions\Audit\WriteAuditLog;
use App\Actions\PenanggungJawab\AssignPenanggungJawab;
use App\Actions\PenanggungJawab\MonitorPenanggungJawab;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PenanggungJawabTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    private IndikatorKinerja $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['status' => 'aktif']);
        $this->target = User::factory()->create(['status' => 'aktif']);
        $this->actor->roles()->attach(Role::where('kode', 'perencanaan')->value('id'), [
            'id' => Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
        $unit = Unit::create(['nama' => 'Unit Pengujian PJ', 'created_by' => $this->actor->id]);
        $renstra = Renstra::create(['kode' => 'R-PJ', 'nama' => 'Renstra Pengujian', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-PJ', 'deskripsi' => 'Sasaran Pengujian']);
        $this->indicator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'I-PJ', 'nama' => 'Indikator Pengujian', 'satuan' => 'poin',
            'tipe_perhitungan' => 'manual', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
    }

    public function test_inactive_actor_inertia_mutation_is_audited_without_navigation_or_assignment(): void
    {
        $this->actingAs($this->actor);
        $this->actor->update(['status' => 'nonaktif']);
        $before = AuditLog::count();

        $this->post($this->url(), $this->payload($this->target, '2026-01-01'), ['X-Inertia' => 'true'])
            ->assertForbidden()->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia-Location')
            ->assertHeaderMissing('X-Inertia')->assertJsonPath('message', 'Akun tidak aktif. Periksa status akun sebelum melanjutkan.');
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $this->assertDatabaseCount('audit_log', $before + 1);
        $audit = AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame($this->indicator->id, $audit->objek_id);
        $this->assertSame('indikator', $audit->objek_tipe);
        $this->assertSame('ditolak', $audit->nilai_baru['hasil']);
        $this->assertSame('penanggung_jawab:update', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
        // Navigasi biasa tetap memakai halaman onboarding existing.
        $this->get($this->url())->assertRedirect('/auth/pending');
        $this->assertDatabaseCount('audit_log', $before + 1);
    }

    #[DataProvider('inactiveMutationRequests')]
    public function test_inactive_actor_replacement_is_audited_once_and_preserves_history(bool $inertia): void
    {
        $row = $this->assignment($this->target, '2026-01-01');
        $history = $row->fresh()->getRawOriginal();
        $this->actingAs($this->actor);
        $this->actor->update(['status' => 'nonaktif']);
        $response = $this->post($this->url().'/pergantian', $this->payload($this->actor, '2026-02-01', 'Pergantian'), $inertia ? ['X-Inertia' => 'true'] : []);
        if ($inertia) {
            $response->assertForbidden()->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia-Location');
        } else {
            $response->assertRedirect('/auth/pending');
        }
        $this->assertDatabaseCount('penanggung_jawab', 1);
        $this->assertSame($history, $row->fresh()->getRawOriginal());
        $audit = AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame($this->indicator->id, $audit->objek_id);
        $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
    }

    public static function inactiveMutationRequests(): array
    {
        return [[true], [false]];
    }

    public function test_inactive_standard_initial_request_is_audited_without_copying_payload(): void
    {
        $this->actingAs($this->actor);
        $this->actor->update(['status' => 'nonaktif']);
        $payload = $this->payload($this->target, '2026-01-01') + ['indikator_id' => (string) Str::uuid(), 'token' => 'payload-tidak-boleh-diaudit'];
        $payload['alasan'] = 'payload-tidak-boleh-diaudit';
        $this->post($this->url(), $payload)->assertRedirect('/auth/pending');
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $audit = AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->sole();
        $this->assertSame($this->indicator->id, $audit->objek_id);
        $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
        $this->assertStringNotContainsString('payload-tidak-boleh-diaudit', $audit->toJson());
    }

    public function test_inactive_non_pj_and_invalid_object_requests_do_not_create_pj_audit(): void
    {
        $this->actingAs($this->actor);
        $this->actor->update(['status' => 'nonaktif']);
        $this->post('/unit', ['nama' => 'Unit tidak boleh dibuat'], ['X-Inertia' => 'true'])->assertForbidden();
        $this->post('/perencanaan/indikator/bukan-uuid/penanggung-jawab', [], ['X-Inertia' => 'true'])->assertNotFound();
        $this->post('/perencanaan/indikator/'.Str::uuid().'/penanggung-jawab', [], ['X-Inertia' => 'true'])->assertNotFound();
        $this->assertSame(0, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    public function test_same_date_replacement_appends_and_latest_order_wins(): void
    {
        $first = $this->assignment($this->target, '2026-02-01');
        $future = $this->assignment($this->actor, '2026-04-01')->fresh()->getRawOriginal();
        $change = fn (User $user) => $this->actingAs($this->actor)->from($this->url())
            ->post($this->url().'/pergantian', $this->payload($user, '2026-02-01', 'Pergantian hari yang sama'));
        $change($this->actor)->assertSessionHasNoErrors();
        $change($this->actor)->assertSessionHasErrors('user_id');
        $change($this->target)->assertSessionHasNoErrors();
        // Waktu dibekukan sehingga created_at identik; pemenang hanya ditentukan urutan penugasan.
        $latest = PenugasanIndikator::where('user_id', $this->target->id)->where('tanggal_mulai_berlaku', '2026-02-01')->whereKeyNot($first->id)->sole();
        $this->assertSame($future, PenugasanIndikator::where('tanggal_mulai_berlaku', '2026-04-01')->sole()->getRawOriginal());
        $this->assertSame(2, AuditLog::where('tindakan', 'penanggung_jawab.ganti')->count());
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('effective.id', $latest->id)->where('history.data.0.state', 'Terjadwal')
            ->where('history.data.1.id', $latest->id)->where('history.data.1.state', 'Efektif')
            ->where('history.data.2.pic.id', $this->actor->id)->where('history.data.2.state', 'Riwayat')
            ->where('history.data.3.id', $first->id)->where('history.data.3.state', 'Riwayat'));
    }

    public function test_superseded_future_row_is_not_scheduled_and_uuid_order_never_picks_winner(): void
    {
        $this->assignment($this->target, '2026-02-01');
        $this->assignment($this->actor, '2026-04-01');
        // UUIDv7 ikut waktu; UUID terkecil pada baris terakhir membuktikan pemenang hanya dari `urutan`.
        $latest = PenugasanIndikator::forceCreate(['id' => '00000000-0000-7000-8000-000000000001', 'indikator_id' => $this->indicator->id,
            'user_id' => $this->target->id, 'tanggal_mulai_berlaku' => '2026-04-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertSame($latest->id, PenugasanIndikator::effectiveOn('2026-04-01')->where('indikator_id', $this->indicator->id)->sole()->id);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('history.data.0.id', $latest->id)->where('history.data.0.state', 'Terjadwal')
            ->where('history.data.1.pic.id', $this->actor->id)->where('history.data.1.state', 'Riwayat'));
    }

    public function test_same_date_winner_follows_urutan_not_physical_insert_order(): void
    {
        // Baris yang disisipkan lebih dulu diberi `urutan` lebih besar; resolver tanpa tie-break
        // `urutan` akan mengikuti urutan sisip fisik dan memilih baris kedua.
        DB::insert('insert into penanggung_jawab (id, indikator_id, user_id, tanggal_mulai_berlaku, ditetapkan_oleh, created_at, urutan)
            overriding system value values (?, ?, ?, ?, ?, ?, ?)', [
            (string) Str::uuid7(), $this->indicator->id, $this->target->id, '2026-02-01', $this->actor->id, now(), 1000000,
        ]);
        $this->assignment($this->actor, '2026-02-01');

        $this->assertSame($this->target->id, PenugasanIndikator::effectiveOn('2026-02-01')->where('indikator_id', $this->indicator->id)->sole()->user_id);
    }

    public function test_same_date_migration_rollback_refuses_twin_dates_and_round_trips_otherwise(): void
    {
        $migration = require database_path('migrations/2026_10_09_000002_allow_same_date_penanggung_jawab.php');
        $indexes = fn () => DB::table('pg_indexes')->where('tablename', 'penanggung_jawab')->orderBy('indexname')->pluck('indexdef', 'indexname')->all();
        $before = $indexes();
        $first = $this->assignment($this->target, '2026-01-01');
        $twin = $this->assignment($this->actor, '2026-01-01');
        try {
            $migration->down();
            $this->fail('Rollback harus ditolak tanpa menyentuh histori.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tanggal kembar', $exception->getMessage());
        }
        $this->assertSame(2, PenugasanIndikator::where('tanggal_mulai_berlaku', '2026-01-01')->count());
        $this->assertSame($before, $indexes());
        // Transaksi test hanya dipakai untuk membuktikan rollback tanpa kembar; histori produksi tetap append-only.
        DB::table('penanggung_jawab')->where('id', $twin->id)->delete();
        $migration->down();
        $this->assertFalse(Schema::hasColumn('penanggung_jawab', 'urutan'));
        $this->assertArrayHasKey('penanggung_jawab_indikator_tanggal_unique', $indexes());
        $this->assertArrayNotHasKey('penanggung_jawab_indikator_id_tanggal_mulai_berlaku_index', $indexes());
        $migration->up();
        $this->assertSame($before, $indexes());
        $this->assertNotNull($first->fresh()->urutan);
        $this->assertNotNull($this->assignment($this->actor, '2026-01-01')->fresh()->urutan);
    }

    public function test_migration_refuses_legacy_duplicate_dates_without_changing_history(): void
    {
        // Schema terkini sudah non-unique, sama dengan kondisi sebelum migration 2026_10_06.
        $this->assignment($this->target, '2026-01-01');
        $this->assignment($this->actor, '2026-01-01');
        $before = DB::table('penanggung_jawab')->orderBy('id')->get()->toArray();
        $indexes = DB::select("select indexname, indexdef from pg_indexes where tablename = 'penanggung_jawab' order by indexname");
        $migration = require database_path('migrations/2026_10_06_000001_add_unique_indicator_date_to_penanggung_jawab.php');
        try {
            $migration->up();
            $this->fail('Migrasi tidak boleh memilih atau menghapus histori duplikat otomatis.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tanggal duplikat', $exception->getMessage());
        }
        $this->assertEquals($before, DB::table('penanggung_jawab')->orderBy('id')->get()->toArray());
        $this->assertEquals($indexes, DB::select("select indexname, indexdef from pg_indexes where tablename = 'penanggung_jawab' order by indexname"));
    }

    public function test_year_zero_is_rejected_before_postgresql_queries(): void
    {
        $this->actingAs($this->actor)
            ->getJson('/penanggung-jawab?tanggal_acuan=0000-01-01')
            ->assertUnprocessable()->assertJsonValidationErrors('tanggal_acuan');
        $this->postJson($this->url(), [
            'user_id' => $this->target->id, 'tanggal_mulai_berlaku' => '0000-01-01',
            'expected_state' => $this->indicator->assignmentStateToken(),
        ])->assertUnprocessable()->assertJsonValidationErrors('tanggal_mulai_berlaku');
        $this->assertDatabaseCount('penanggung_jawab', 0);
    }

    public function test_active_target_uuid_is_case_insensitive(): void
    {
        $payload = $this->payload($this->target, '2026-01-01');
        $payload['user_id'] = strtoupper($this->target->id);
        $this->actingAs($this->actor)->post($this->url(), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('penanggung_jawab', ['indikator_id' => $this->indicator->id, 'user_id' => $this->target->id]);
    }

    public function test_history_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $row = $this->assignment($this->target, '2026-01-01');
        try {
            $row->update(['user_id' => $this->actor->id]);
            $this->fail('Histori assignment tidak boleh diubah.');
        } catch (LogicException) {
            $this->assertSame($this->target->id, $row->fresh()->user_id);
        }
        $this->expectException(LogicException::class);
        $row->fresh()->delete();
    }

    public function test_shared_temporal_query_uses_requested_date_and_filters_user_after_winner(): void
    {
        $this->assignment($this->target, '2026-01-01');
        $this->assignment($this->actor, '2026-02-01');
        $this->assignment($this->target, '2026-04-01');
        foreach (['2025-12-31' => null, '2026-01-01' => $this->target->id, '2026-01-31' => $this->target->id, '2026-02-01' => $this->actor->id, '2026-03-15' => $this->actor->id, '2026-04-01' => $this->target->id] as $date => $userId) {
            $this->assertSame($userId, PenugasanIndikator::effectiveOn($date)->where('indikator_id', $this->indicator->id)->value('user_id'));
        }
        $this->assertFalse(PenugasanIndikator::effectiveOn('2026-03-15')->where('user_id', $this->target->id)->exists());
    }

    public function test_first_assignment_accepts_active_user_without_role_or_grant_and_warns(): void
    {
        $this->actingAs($this->actor)->post($this->url(), $this->payload($this->target, '2026-01-01'))
            ->assertRedirect($this->url())->assertSessionHas('warning');
        $this->assertDatabaseHas('penanggung_jawab', ['indikator_id' => $this->indicator->id, 'user_id' => $this->target->id, 'alasan' => null, 'ditetapkan_oleh' => $this->actor->id]);
        $this->assertSame(0, $this->target->roles()->count());
        $this->assertSame(0, DB::table('user_permission_granted')->where('user_id', $this->target->id)->count());
        $audit = AuditLog::where('tindakan', 'penanggung_jawab.tetapkan')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertNull($audit->nilai_lama['user_id']);
        $this->assertSame($this->target->id, $audit->nilai_baru['user_id']);
        $this->assertSame('2026-01-01', $audit->nilai_baru['tanggal_mulai_berlaku']);
        $this->assertSame('penanggung_jawab:update', $audit->dasar_izin['permission']);
        $this->assertSame(0, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    public function test_inactive_target_and_direct_request_without_permission_are_rejected(): void
    {
        $this->target->update(['status' => 'nonaktif']);
        $this->actingAs($this->actor)->from($this->url())->post($this->url(), $this->payload($this->target, '2026-01-01'))
            ->assertSessionHasErrors('user_id');
        $this->target->update(['status' => 'aktif']);
        $this->attachRole($this->target, 'pegawai');
        $this->actingAs($this->target)->post($this->url(), $this->payload($this->target, '2026-01-01'))->assertForbidden();
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->where('actor_id', $this->target->id)->count());
    }

    public function test_replacement_keeps_history_and_allows_return_after_another_user(): void
    {
        $first = $this->assignment($this->target, '2026-01-01');
        $before = $first->fresh()->getRawOriginal();
        $this->actingAs($this->actor)->post($this->url().'/pergantian', $this->payload($this->actor, '2026-02-01', 'Pergantian tanggung jawab'))
            ->assertRedirect($this->url());
        $this->actingAs($this->actor)->post($this->url().'/pergantian', $this->payload($this->target, '2026-04-01', 'Kembali bertanggung jawab'))
            ->assertRedirect($this->url());
        $this->assertSame($before, $first->fresh()->getRawOriginal());
        $this->assertDatabaseCount('penanggung_jawab', 3);
        $audit = AuditLog::where('tindakan', 'penanggung_jawab.ganti')->where('nilai_baru->tanggal_mulai_berlaku', '2026-04-01')->sole();
        $this->assertSame($this->actor->id, $audit->nilai_lama['user_id']);
        $this->assertSame('2026-04-01', $audit->nilai_lama['tanggal_acuan']);
    }

    public function test_noop_uses_requested_date_and_does_not_rewrite_future_history(): void
    {
        $this->assignment($this->target, '2026-01-01');
        $this->assignment($this->actor, '2026-04-01');
        $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $this->payload($this->target, '2026-02-01', 'Tidak mengubah PJ'))
            ->assertSessionHasErrors('user_id');
        $this->assertDatabaseCount('penanggung_jawab', 2);
        $future = PenugasanIndikator::where('tanggal_mulai_berlaku', '2026-04-01')->sole()->getRawOriginal();
        $this->actingAs($this->actor)->post($this->url().'/pergantian', $this->payload($this->actor, '2026-02-01', 'Pergantian backdate'))
            ->assertRedirect($this->url());
        $this->assertSame($future, PenugasanIndikator::where('tanggal_mulai_berlaku', '2026-04-01')->sole()->getRawOriginal());
        $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.ganti')->count());
    }

    public function test_empty_reason_and_stale_backdate_are_audited_without_insert(): void
    {
        $this->assignment($this->target, '2026-02-01');
        $oldPayload = $this->payload($this->actor, '2026-04-01', 'Pergantian');
        $this->assignment($this->actor, '2026-01-01');
        $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $oldPayload)->assertSessionHasErrors('expected_state');
        foreach (['', '   ', "\x01"] as $reason) {
            $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $this->payload($this->actor, '2026-03-01', $reason))->assertSessionHasErrors('alasan');
        }
        $this->assertDatabaseCount('penanggung_jawab', 2);
        $this->assertSame(4, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    #[DataProvider('blockedContexts')]
    public function test_lifecycle_rejects_initial_and_replacement_but_history_is_readable(string $context): void
    {
        match ($context) {
            'indicator' => $this->indicator->update(['status' => 'arsip']),
            'renstra' => $this->indicator->sasaranStrategis->renstra->update(['status' => 'diarsipkan']),
            'unit' => $this->indicator->unit->update(['status' => 'nonaktif']),
        };
        $this->actingAs($this->actor)->from($this->url())->post($this->url(), $this->payload($this->target, '2026-01-01'))->assertSessionHasErrors('indikator');
        $row = $this->assignment($this->target, '2026-01-01');
        $before = $row->fresh()->getRawOriginal();
        $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $this->payload($this->actor, '2026-02-01', 'Pergantian'))->assertSessionHasErrors('indikator');
        $this->get($this->url())->assertOk()->assertInertia(fn (Assert $page) => $page->component('PenanggungJawab/Show')->has('history.data', 1)->where('can.assign', false));
        $this->assertSame($before, $row->fresh()->getRawOriginal());
        $this->assertDatabaseCount('penanggung_jawab', 1);
        $this->assertSame(2, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
    }

    public static function blockedContexts(): array
    {
        return [['indicator'], ['renstra'], ['unit']];
    }

    public function test_boundary_rechecks_actor_and_audit_failure_rolls_back(): void
    {
        $payload = $this->payload($this->target, '2026-01-01');
        $this->actor->update(['status' => 'nonaktif']);
        try {
            app(AssignPenanggungJawab::class)->handle($this->actor, $this->indicator, $payload);
            $this->fail('Aktor nonaktif harus ditolak.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('penanggung_jawab', 0);
            $this->assertSame(1, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
        }
        $this->actor->update(['status' => 'aktif']);
        $this->mock(WriteAuditLog::class, function ($mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit fixture gagal'));
        });
        try {
            app(AssignPenanggungJawab::class)->handle($this->actor, $this->indicator, $payload);
            $this->fail('Audit gagal harus membatalkan assignment.');
        } catch (RuntimeException $error) {
            $this->assertSame('Audit fixture gagal', $error->getMessage());
        }
        $this->assertDatabaseCount('penanggung_jawab', 0);
    }

    public function test_readiness_lists_all_permissions_and_deny_wins_in_indicator_unit(): void
    {
        $this->attachRole($this->target, 'pegawai');
        $permission = Permission::where('kode', 'pengukuran:create')->sole();
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id, 'permission_id' => $permission->id, 'unit_id' => $this->indicator->unit_id, 'alasan' => 'Fixture grant', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $response = $this->actingAs($this->actor)->getJson($this->url().'/hak-kerja?user_id='.$this->target->id)->assertOk()->assertJsonCount(7, 'permissions')->assertJsonCount(6, 'missing');
        $this->assertTrue(collect($response->json('permissions'))->firstWhere('permission', 'pengukuran:create')['allowed']);
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id, 'permission_id' => $permission->id, 'unit_id' => $this->indicator->unit_id, 'alasan' => 'Fixture deny', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->getJson($this->url().'/hak-kerja?user_id='.$this->target->id)->assertOk()->assertJsonCount(7, 'missing')->assertJsonPath('permissions.0.reason', 'explicit_deny');
    }

    public function test_monitoring_filters_effective_active_pj_and_keeps_permission_results(): void
    {
        $this->assignment($this->target, '2026-01-01');
        $this->assignment($this->actor, '2026-04-01');
        $this->actingAs($this->actor)->get('/penanggung-jawab?tanggal_acuan=2026-03-15')->assertOk()->assertInertia(fn (Assert $page) => $page->component('PenanggungJawab/Index')->has('assignments.data', 1)->where('assignments.data.0.pic.id', $this->target->id)->has('assignments.data.0.readiness.missing', 7));
        $this->get('/penanggung-jawab?tanggal_acuan=2026-04-01')->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0));
        $this->target->update(['status' => 'nonaktif']);
        $this->get('/penanggung-jawab?tanggal_acuan=2026-03-15')->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0));
    }

    private function attachRole(User $user, string $code): void
    {
        $user->roles()->attach(Role::where('kode', $code)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function url(): string
    {
        return '/perencanaan/indikator/'.$this->indicator->id.'/penanggung-jawab';
    }

    private function payload(User $user, string $date, ?string $reason = null): array
    {
        return ['user_id' => $user->id, 'tanggal_mulai_berlaku' => $date, 'alasan' => $reason, 'expected_state' => $this->indicator->fresh()->assignmentStateToken()];
    }

    #[DataProvider('monitorPageBoundaries')]
    public function test_monitor_pagination_does_not_skip_extra_incomplete_row(int $count): void
    {
        $expected = [];
        for ($i = 0; $i < $count; $i++) {
            $indicator = $this->indicator->replicate();
            $indicator->kode = 'I-PJ-PAGE-'.$i;
            $indicator->save();
            $expected[] = $indicator->id;
            PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $this->target->id,
                'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        $action = app(MonitorPenanggungJawab::class);
        $first = $action->handle(['tanggal_acuan' => '2026-03-15']);
        $this->assertCount(min(20, $count), $first['assignments']['data']);
        $firstIds = array_column(array_column($first['assignments']['data'], 'indicator'), 'id');
        sort($expected);
        if ($count <= 20) {
            $this->assertSame($expected, $firstIds);
            $this->assertNull($first['assignments']['next_page_url']);

            return;
        }
        parse_str(parse_url($first['assignments']['next_page_url'], PHP_URL_QUERY), $filters);
        $second = $action->handle($filters);
        $this->assertCount(1, $second['assignments']['data']);
        $ids = array_column(array_column([...$first['assignments']['data'], ...$second['assignments']['data']], 'indicator'), 'id');
        $this->assertCount(21, array_unique($ids));
        $this->assertSame($expected, $ids);
        $this->assertNull($second['assignments']['next_page_url']);
        // Continuation harus lolos validasi HTTP dan diteruskan utuh ke Action.
        $this->actingAs($this->actor)->get($first['assignments']['next_page_url'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('assignments.data', 1)
                ->where('assignments.data.0.indicator.id', $second['assignments']['data'][0]['indicator']['id'])
                ->where('assignments.next_page_url', null));
        $this->getJson('/penanggung-jawab?after_scope=bukan-scope')->assertUnprocessable()->assertJsonValidationErrors('after_scope');
    }

    public static function monitorPageBoundaries(): array
    {
        return [[19], [20], [21]];
    }

    public function test_monitoring_preserves_unit_grant_deny_and_unknown_permission_diagnoses(): void
    {
        $this->attachRole($this->target, 'pegawai');
        $this->assignment($this->target, '2026-01-01');
        $permission = Permission::where('kode', 'pengukuran:create')->sole();
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id,
            'permission_id' => $permission->id, 'unit_id' => $this->indicator->unit_id,
            'alasan' => 'Fixture grant monitoring', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $filters = ['tanggal_acuan' => '2026-03-15'];
        $granted = app(MonitorPenanggungJawab::class)->handle($filters)['assignments']['data'][0]['readiness'];
        $this->assertCount(6, $granted['missing']);
        $this->assertTrue($granted['permissions'][0]['allowed']);
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id,
            'permission_id' => $permission->id, 'unit_id' => $this->indicator->unit_id,
            'alasan' => 'Fixture deny monitoring', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $denied = app(MonitorPenanggungJawab::class)->handle($filters)['assignments']['data'][0]['readiness'];
        $this->assertCount(7, $denied['missing']);
        $this->assertFalse($denied['permissions'][0]['allowed']);
        $this->assertSame('explicit_deny', $denied['permissions'][0]['reason']);
        $permission->update(['aktif' => false]);
        $unknown = app(MonitorPenanggungJawab::class)->handle($filters)['assignments']['data'][0]['readiness'];
        $this->assertFalse($unknown['permissions'][0]['allowed']);
        $this->assertSame('unknown_permission', $unknown['permissions'][0]['reason']);
    }

    public function test_monitoring_continues_after_twenty_matches_when_ready_candidates_remain(): void
    {
        $ids = $this->monitoringCandidates(25, range(1, 20));
        $first = app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15']);
        $this->assertSame(array_values(array_slice($ids, 0, 20, true)), array_column(array_column($first['assignments']['data'], 'indicator'), 'id'));
        $this->assertNotNull($first['assignments']['next_page_url']);
        parse_str(parse_url($first['assignments']['next_page_url'], PHP_URL_QUERY), $filters);
        $this->assertSame($ids[20], $filters['after']);
        $second = app(MonitorPenanggungJawab::class)->handle($filters);
        $this->assertSame([], $second['assignments']['data']);
        $this->assertNull($second['assignments']['next_page_url']);
    }

    public function test_monitoring_continuation_reads_live_changes_without_rewinding_processed_candidates(): void
    {
        $ids = $this->monitoringCandidates(120, [110]);
        $first = app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15']);
        $this->assertSame([], $first['assignments']['data']);
        parse_str(parse_url($first['assignments']['next_page_url'], PHP_URL_QUERY), $filters);
        $this->assertSame($ids[100], $filters['after']);
        $permissionId = Permission::where('kode', 'pengukuran:create')->value('id');
        foreach ([50, 101] as $position) {
            $assignment = PenugasanIndikator::where('indikator_id', $ids[$position])->sole();
            DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $assignment->user_id,
                'permission_id' => $permissionId, 'unit_id' => $this->indicator->unit_id,
                'alasan' => 'Fixture pencabutan hak saat pagination', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        IndikatorKinerja::whereKey($ids[101])->update(['status' => 'arsip']);
        $inactive = PenugasanIndikator::where('indikator_id', $ids[105])->sole();
        User::whereKey($inactive->user_id)->update(['status' => 'nonaktif']);
        $old = PenugasanIndikator::where('indikator_id', $ids[110])->sole();
        $history = $old->fresh()->getRawOriginal();
        PenugasanIndikator::create(['indikator_id' => $ids[110], 'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-02-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $next = app(MonitorPenanggungJawab::class)->handle($filters);
        $this->assertSame([$ids[101]], array_column(array_column($next['assignments']['data'], 'indicator'), 'id'));
        $this->assertSame('explicit_deny', $next['assignments']['data'][0]['readiness']['permissions'][0]['reason']);
        $this->assertNotNull($next['assignments']['data'][0]['blocked_reason']);
        $this->assertNull($next['assignments']['next_page_url']);
        $this->assertSame($history, $old->fresh()->getRawOriginal());
        $this->assertDatabaseHas('penanggung_jawab', ['id' => $inactive->id, 'user_id' => $inactive->user_id]);
        // Perubahan di belakang cursor terlihat saat scan baru; continuation bukan snapshot beku.
        $fresh = app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15']);
        $this->assertSame([$ids[50]], array_column(array_column($fresh['assignments']['data'], 'indicator'), 'id'));
    }

    public function test_user_options_are_active_bounded_and_require_management_permission(): void
    {
        User::factory()->count(21)->create(['nama' => 'Calon PJ', 'status' => 'aktif']);
        User::factory()->create(['nama' => 'Calon PJ Nonaktif', 'status' => 'nonaktif']);
        $response = $this->actingAs($this->actor)->getJson('/penanggung-jawab/opsi/pengguna?q=Calon');
        $response->assertOk()->assertJsonCount(20, 'items')->assertJsonPath('hasMore', true);
        foreach ($response->json('items') as $row) {
            $this->assertSame('aktif', $row['status']);
            $this->assertSame(['id', 'nama', 'email', 'status', 'roles'], array_keys($row));
        }
        $this->getJson('/penanggung-jawab/opsi/pengguna?q=Calon&page=2')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('hasMore', false);
        $this->actingAs($this->target)->getJson('/penanggung-jawab/opsi/pengguna?q=Calon')->assertForbidden();
    }

    public function test_monitoring_bounds_candidates_even_when_all_users_are_ready(): void
    {
        $this->monitoringCandidates(250);
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $result = app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15']);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertSame([], $result['assignments']['data']);
        $this->assertNotNull($result['assignments']['next_page_url']);
        $aclQueries = array_filter($queries, fn ($query) => str_contains($query['query'], 'from "permissions"'));
        $this->assertCount(100, $aclQueries);
        $this->assertLessThanOrEqual(550, count($queries));
    }

    public function test_sparse_incomplete_rows_are_found_across_empty_scan_pages(): void
    {
        $ids = $this->monitoringCandidates(250, [125, 240]);
        $filters = ['tanggal_acuan' => '2026-03-15', 'q' => 'MON-', 'unit_id' => $this->indicator->unit_id];
        $found = [];
        $cursors = [];
        $pages = 0;
        do {
            $result = app(MonitorPenanggungJawab::class)->handle($filters);
            if (++$pages === 1) {
                $this->assertSame([], $result['assignments']['data']);
                $this->assertNotNull($result['assignments']['next_page_url']);
            }
            $found = [...$found, ...array_column(array_column($result['assignments']['data'], 'indicator'), 'id')];
            $next = $result['assignments']['next_page_url'];
            if ($next !== null) {
                parse_str(parse_url($next, PHP_URL_QUERY), $filters);
                $this->assertSame('MON-', $filters['q']);
                $this->assertSame($this->indicator->unit_id, $filters['unit_id']);
                $this->assertSame('2026-03-15', $filters['tanggal_acuan']);
                $this->assertGreaterThan($cursors === [] ? '' : end($cursors), $filters['after']);
                $cursors[] = $filters['after'];
            }
            $this->assertLessThanOrEqual(3, $pages);
        } while ($next !== null);
        $this->assertSame([$ids[125], $ids[240]], $found);
        $this->assertCount(2, array_unique($found));
        $this->assertSame(3, $pages);
    }

    public function test_monitoring_restarts_cursor_when_search_date_or_unit_changes(): void
    {
        $ids = $this->monitoringCandidates(120, [50, 120]);
        $first = app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15']);
        parse_str(parse_url($first['assignments']['next_page_url'], PHP_URL_QUERY), $continuation);
        foreach ([['q' => 'MON-00050'], ['tanggal_acuan' => '2026-02-01'], ['unit_id' => $this->indicator->unit_id]] as $change) {
            $result = app(MonitorPenanggungJawab::class)->handle(array_replace($continuation, $change));
            $this->assertSame($ids[50], $result['assignments']['data'][0]['indicator']['id']);
        }
        $missingScope = $continuation;
        unset($missingScope['after_scope']);
        $result = app(MonitorPenanggungJawab::class)->handle($missingScope);
        $this->assertSame($ids[50], $result['assignments']['data'][0]['indicator']['id']);
    }

    /** Fixture sintetis berurutan; setiap pasangan user/unit berbeda agar biaya ACL terukur. */
    protected function monitoringCandidates(int $count, array $incompleteAt = []): array
    {
        $users = User::factory()->count($count)->create(['status' => 'aktif']);
        $roleId = Role::where('kode', 'perencanaan')->value('id');
        $indicators = [];
        $assignments = [];
        $roles = [];
        $ids = [];
        foreach ($users as $index => $user) {
            $position = $index + 1;
            $id = sprintf('00000000-0000-4000-8000-%012d', $position);
            $ids[$position] = $id;
            $indicators[] = array_replace($this->indicator->getAttributes(), ['id' => $id, 'kode' => sprintf('MON-%05d', $position)]);
            $assignments[] = ['id' => (string) Str::uuid(), 'indikator_id' => $id, 'user_id' => $user->id,
                'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()];
            if (! in_array($position, $incompleteAt, true)) {
                $roles[] = ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $roleId,
                    'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()];
            }
        }
        DB::table('indikator_kinerjas')->insert($indicators);
        DB::table('penanggung_jawab')->insert($assignments);
        if ($roles !== []) {
            DB::table('user_roles')->insert($roles);
        }

        return $ids;
    }

    private function assignment(User $user, string $date): PenugasanIndikator
    {
        return PenugasanIndikator::create([
            'indikator_id' => $this->indicator->id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => $date, 'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }
}
