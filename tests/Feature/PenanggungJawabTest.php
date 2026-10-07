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
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
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

    public function test_inactive_actor_inertia_mutation_is_rejected_without_navigation_or_writes(): void
    {
        $this->actingAs($this->actor);
        $this->actor->update(['status' => 'nonaktif']);
        $before = AuditLog::count();

        $this->post($this->url(), $this->payload($this->target, '2026-01-01'), ['X-Inertia' => 'true'])
            ->assertForbidden()->assertHeaderMissing('Location')->assertHeaderMissing('X-Inertia-Location')
            ->assertHeaderMissing('X-Inertia')->assertJsonPath('message', 'Akun tidak aktif. Periksa status akun sebelum melanjutkan.');
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $this->assertDatabaseCount('audit_log', $before);
        // Navigasi biasa tetap memakai halaman onboarding existing.
        $this->get($this->url())->assertRedirect('/auth/pending');
    }

    public function test_database_rejects_two_assignments_on_the_same_indicator_date(): void
    {
        $this->assignment($this->target, '2026-01-01');
        $this->expectException(QueryException::class);
        $this->assignment($this->actor, '2026-01-01');
    }

    public function test_migration_refuses_legacy_duplicate_dates_without_changing_history(): void
    {
        // Simulasikan schema sebelum constraint baru, hanya pada transaksi PostgreSQL disposable.
        Schema::table('penanggung_jawab', function (Blueprint $table): void {
            $table->dropUnique('penanggung_jawab_indikator_tanggal_unique');
            $table->index(['indikator_id', 'tanggal_mulai_berlaku']);
        });
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

    public function test_duplicate_date_empty_reason_and_stale_backdate_are_audited_without_insert(): void
    {
        $this->assignment($this->target, '2026-02-01');
        $oldPayload = $this->payload($this->actor, '2026-04-01', 'Pergantian');
        $this->assignment($this->actor, '2026-01-01');
        $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $oldPayload)->assertSessionHasErrors('expected_state');
        $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $this->payload($this->actor, '2026-02-01', 'Tanggal sama'))->assertSessionHasErrors('tanggal_mulai_berlaku');
        foreach (['', '   ', "\x01"] as $reason) {
            $this->actingAs($this->actor)->from($this->url())->post($this->url().'/pergantian', $this->payload($this->actor, '2026-03-01', $reason))->assertSessionHasErrors('alasan');
        }
        $this->assertDatabaseCount('penanggung_jawab', 2);
        $this->assertSame(5, AuditLog::where('tindakan', 'penanggung_jawab.ditolak')->count());
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

    public function test_monitor_pagination_does_not_skip_extra_incomplete_row(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $indicator = $this->indicator->replicate();
            $indicator->kode = 'I-PJ-PAGE-'.$i;
            $indicator->save();
            PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $this->target->id,
                'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        $action = app(MonitorPenanggungJawab::class);
        $first = $action->handle(['tanggal_acuan' => '2026-03-15']);
        $this->assertCount(20, $first['assignments']['data']);
        parse_str(parse_url($first['assignments']['next_page_url'], PHP_URL_QUERY), $filters);
        $second = $action->handle($filters);
        $this->assertCount(1, $second['assignments']['data']);
        $ids = array_column(array_column([...$first['assignments']['data'], ...$second['assignments']['data']], 'indicator'), 'id');
        $this->assertCount(21, array_unique($ids));
        $this->assertNull($second['assignments']['next_page_url']);
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

    private function assignment(User $user, string $date): PenugasanIndikator
    {
        return PenugasanIndikator::create([
            'indikator_id' => $this->indicator->id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => $date, 'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }
}
