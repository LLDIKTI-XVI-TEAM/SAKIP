<?php

namespace Tests\Feature\Authorization;

use App\Actions\Access\AssignRole;
use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleAssignmentReceipt;
use App\Services\Authorization\RoleAssignmentWarnings;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class AssignRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    private int $seedAudits;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->seedAudits = DB::table('audit_log')->count();
        $this->actor = User::factory()->create(['nama' => 'Z Pengelola', 'status' => 'aktif']);
        $this->target = User::factory()->create(['status' => 'nonaktif']);
        $role = Role::where('kode', 'superadmin')->sole();
        $this->actor->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->startSession();
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    public function test_first_assignment_accepts_official_role_and_change_preserves_one_pivot_and_historical_audit(): void
    {
        $action = app(AssignRole::class);
        $pimpinan = Role::where('kode', 'pimpinan')->sole();
        $this->assertSame(['status' => 'assigned', 'has_active_pj' => false], $action->handle($this->actor, $this->target->id, $pimpinan->id, '  Penugasan awal  ', null));
        $before = DB::table('user_roles')->where('user_id', $this->target->id)->sole();
        $audit = AuditLog::findOrFail($before->audit_id);
        $this->assertSame('user_roles.tambah', $audit->tindakan);
        $this->assertNull($audit->nilai_lama);
        $this->assertSame(['role_id' => $pimpinan->id, 'role_kode' => 'pimpinan'], $audit->nilai_baru);
        $this->assertSame('Penugasan awal', $audit->alasan);
        $this->assertTrue($audit->dasar_izin['pengguna_read']['allowed']);
        $this->assertTrue($audit->dasar_izin['akses_update']['allowed']);
        $this->assertSame($this->actor->id, $audit->actor_id);
        $history = $audit->getRawOriginal();
        $pegawai = Role::where('kode', 'pegawai')->sole();
        $this->assertSame(['status' => 'changed', 'has_active_pj' => false], $action->handle($this->actor, $this->target->id, $pegawai->id, 'Pergantian', $this->token()));
        $after = DB::table('user_roles')->where('user_id', $this->target->id)->sole();
        $this->assertSame($before->id, $after->id);
        $this->assertSame($before->created_at, $after->created_at);
        $this->assertSame($this->actor->id, $after->diberikan_oleh);
        $this->assertSame('manual', $after->sumber_pemberian);
        $this->assertSame($pegawai->id, $after->role_id);
        $change = AuditLog::findOrFail($after->audit_id);
        $this->assertSame('user_roles.ubah', $change->tindakan);
        $this->assertSame(['role_id' => $pimpinan->id, 'role_kode' => 'pimpinan'], $change->nilai_lama);
        $this->assertSame(['role_id' => $pegawai->id, 'role_kode' => 'pegawai'], $change->nilai_baru);
        $this->assertSame($history, $audit->fresh()->getRawOriginal());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $this->target->id)->count());
        $this->assertSame('nonaktif', $this->target->fresh()->status);
    }

    #[DataProvider('effectiveAssignments')]
    public function test_active_pj_warning_uses_effective_assignment_not_history_or_future(array $assignments, bool $expected): void
    {
        $this->travelTo(Carbon::parse('2026-09-28T16:30:00Z'));
        $indicator = $this->indicator();
        foreach ($assignments as [$target, $date, $created]) {
            $this->assignment($indicator, $target ? $this->target : $this->actor, $date, $created);
        }
        $this->actingAs($this->actor)->get('/akses/peran?q='.urlencode($this->target->email))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)->where('users.data.0.has_active_pj', $expected)
            ->missing('users.data.0.penanggung_jawab')->missing('users.data.0.indikator_id'));
    }

    public static function effectiveAssignments(): array
    {
        return [
            'current' => [[[true, '2026-09-01', '2026-09-01 09:00:00']], true],
            'replaced' => [[[true, '2026-09-01', '2026-09-01 09:00:00'], [false, '2026-09-20', '2026-09-20 09:00:00']], false],
            'future only' => [[[true, '2026-09-30', '2026-09-01 09:00:00']], false],
            'future replacement' => [[[true, '2026-09-01', '2026-09-01 09:00:00'], [false, '2026-09-30', '2026-09-20 09:00:00']], true],
            'effective date beats insert time' => [[[true, '2026-09-20', '2026-09-01 09:00:00'], [false, '2026-09-01', '2026-09-20 10:00:00']], true],
            'WITA today' => [[[true, '2026-09-29', '2026-09-28 09:00:00']], true],
            'same date later order replaces' => [[[true, '2026-09-01', '2026-09-01 09:00:00'], [false, '2026-09-01', '2026-09-01 09:00:00']], false],
            'same date later order assigns' => [[[false, '2026-09-01', '2026-09-01 09:00:00'], [true, '2026-09-01', '2026-09-01 09:00:00']], true],
        ];
    }

    public function test_action_outcome_rechecks_pj_and_noop_does_not_append_success_audit(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28T16:30:00Z'));
        $this->target->update(['status' => 'aktif']);
        $this->actingAs($this->actor)->get('/akses/peran?q='.urlencode($this->target->email))->assertOk();
        $this->assignment($this->indicator(), $this->target, '2026-09-29', '2026-09-28 09:00:00');
        $action = app(AssignRole::class);
        $role = Role::where('kode', 'pimpinan')->value('id');
        $this->assertSame(['status' => 'assigned', 'has_active_pj' => true], $action->handle($this->actor, $this->target->id, $role, 'Awal', null));
        $this->assertSame(['status' => 'unchanged', 'has_active_pj' => true], $action->handle($this->actor, $this->target->id, $role, 'Tidak berubah', $this->token()));
        $this->assertDatabaseCount('audit_log', $this->seedAudits + 1);
        $this->assertSame(['status' => 'changed', 'has_active_pj' => true], $action->handle($this->actor, $this->target->id, Role::where('kode', 'pegawai')->value('id'), 'Ganti', $this->token()));
        $this->assertSame('aktif', $this->target->fresh()->status);
    }

    public function test_two_posts_deliver_only_their_own_outcomes_in_reverse_order(): void
    {
        $this->assignment($this->indicator(), $this->target, '2026-01-01', '2026-01-01 09:00:00');
        $this->actingAs($this->actor);
        $first = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303);
        $firstUrl = $first->headers->get('Location');
        parse_str((string) parse_url($firstUrl, PHP_URL_QUERY), $firstQuery);
        $this->assertTrue(Str::isUuid($firstQuery['receipt'] ?? ''), 'POST harus membawa reference operasi.');
        $this->target = User::factory()->create(['status' => 'aktif']);
        $this->target->roles()->attach(Role::where('kode', 'pegawai')->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $second = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303);
        $secondUrl = $second->headers->get('Location');
        parse_str((string) parse_url($secondUrl, PHP_URL_QUERY), $secondQuery);
        $this->assertNotSame($firstQuery['receipt'], $secondQuery['receipt']);
        $firstIndex = $this->get($firstUrl)->assertRedirect()->headers->get('Location');
        $secondIndex = $this->get($secondUrl)->assertRedirect()->headers->get('Location');
        $this->get($secondIndex)->assertInertia(fn (Assert $page) => $page->hasFlash('roleAssignmentOutcome', [
            'receipt_id' => $secondQuery['receipt'], 'status' => 'changed', 'has_active_pj' => false,
        ]));
        $this->get($firstIndex)->assertInertia(fn (Assert $page) => $page->hasFlash('roleAssignmentOutcome', [
            'receipt_id' => $firstQuery['receipt'], 'status' => 'assigned', 'has_active_pj' => true,
        ]));
        $this->get($firstIndex)->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missingFlash('roleAssignmentOutcome'));
        $this->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missing('status')->missingFlash('roleAssignmentOutcome'));
        $this->assertSame(2, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
    }

    public function test_warning_query_failure_rolls_back_role_and_success_audit(): void
    {
        $this->mock(RoleAssignmentWarnings::class)->shouldReceive('forUsers')->with([$this->target->id])->andThrow(new RuntimeException('warning-unavailable'));
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pimpinan')->value('id'), 'Batal', null);
            $this->fail('Warning gagal harus membatalkan mutasi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('warning-unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', $this->seedAudits);
    }

    public function test_pj_query_is_batched_and_never_runs_before_authorization(): void
    {
        $this->actingAs($this->actor);
        DB::enableQueryLog();
        foreach ([0, 18] as $extraUsers) {
            User::factory()->count($extraUsers)->create(['status' => 'aktif']);
            DB::flushQueryLog();
            $this->get('/akses/peran')->assertOk();
            $pjQueries = array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'penanggung_jawab'));
            $this->assertCount(1, $pjQueries);
        }
        $this->deny('pengguna:read');
        DB::flushQueryLog();
        $this->get('/akses/peran')->assertForbidden();
        $this->assertSame([], array_values(array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'penanggung_jawab'))));
        DB::disableQueryLog();
    }

    public function test_receipt_binds_actor_session_and_expiry_and_is_consumed_once(): void
    {
        $receipt = app(RoleAssignmentReceipt::class);
        $reference = $receipt->issue($this->actor->id, 'fixture-session', ['status' => 'assigned', 'has_active_pj' => true]);
        $this->assertNotNull($reference);
        $this->assertNull($receipt->consume($this->target->id, 'fixture-session', $reference));
        $this->assertNull($receipt->consume($this->actor->id, 'other-session', $reference));
        $this->assertSame(['receipt_id' => $reference, 'status' => 'assigned', 'has_active_pj' => true], $receipt->consume($this->actor->id, 'fixture-session', $reference));
        $this->assertNull($receipt->consume($this->actor->id, 'fixture-session', $reference));
        $reference = $receipt->issue($this->actor->id, 'fixture-session', ['status' => 'changed', 'has_active_pj' => false]);
        $this->travel(300)->seconds();
        $this->assertNull($receipt->consume($this->actor->id, 'fixture-session', $reference));
        $this->assertNull($receipt->consume($this->actor->id, 'fixture-session', 'bad-reference'));
    }

    #[DataProvider('receiptFailures')]
    public function test_receipt_delivery_failure_is_unknown(string $operation): void
    {
        $receipt = app(RoleAssignmentReceipt::class);
        $reference = $receipt->issue($this->actor->id, 'fixture-session', ['status' => 'changed', 'has_active_pj' => false]);
        $this->assertNotNull($reference);
        $store = $operation === 'lock' ? \Mockery::mock(DatabaseStore::class) : Cache::store('database')->getStore();
        $repository = \Mockery::mock(Repository::class, [$store])->makePartial();
        if ($operation === 'forget') {
            $repository->shouldReceive('forget')->andReturn(false);
        } elseif ($operation === 'lock') {
            $store->shouldReceive('lock')->andThrow(new RuntimeException('cache-unavailable'));
        } else {
            $repository->shouldReceive($operation)->andThrow(new RuntimeException('cache-unavailable'));
        }
        Cache::partialMock()->shouldReceive('store')->with('database')->andReturn($repository);
        $this->assertNull($receipt->consume($this->actor->id, 'fixture-session', $reference));
        $this->assertDatabaseCount('cache', 1);
    }

    public static function receiptFailures(): array
    {
        return [['lock'], ['get'], ['forget']];
    }

    #[DataProvider('receiptIssueFailures')]
    public function test_cache_issue_failure_after_commit_keeps_one_mutation_and_unknown(string $operation): void
    {
        if ($operation === 'store') {
            Cache::partialMock()->shouldReceive('store')->with('database')->andThrow(new RuntimeException('cache-unavailable'));
        } else {
            $repository = \Mockery::mock(Repository::class, [Cache::store('database')->getStore()])->makePartial();
            $repository->shouldReceive('put')->andReturn(false);
            Cache::partialMock()->shouldReceive('store')->with('database')->andReturn($repository);
        }
        $this->actingAs($this->actor)->post('/akses/peran/'.$this->target->id, $this->payload())
            ->assertStatus(303)->assertRedirect('/akses/peran/hasil');
        $this->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missing('status')->missingFlash('roleAssignmentOutcome'));
        $this->assertSame(1, AuditLog::where('tindakan', 'user_roles.tambah')->count());
        $this->assertSame(1, DB::table('user_roles')->where('user_id', $this->target->id)->count());
    }

    public static function receiptIssueFailures(): array
    {
        return [['store'], ['put']];
    }

    #[DataProvider('unknownConfirmationUrls')]
    public function test_unknown_confirmation_keeps_index_component_and_live_capability(string $url): void
    {
        $this->actingAs($this->actor)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)
            ->where('can.assignRole', true)->where('auth.can.assignRole', true)->has('users.data')
            ->missingFlash('roleAssignmentOutcome')->missing('status'));
        $this->get('/akses/peran')->assertInertia(fn (Assert $page) => $page->where('confirmationUnavailable', false));
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', $this->seedAudits);
    }

    public static function unknownConfirmationUrls(): array
    {
        return [['/akses/peran/hasil'], ['/akses/peran/hasil?receipt=bad'], ['/akses/peran?receipt=bad']];
    }

    public function test_failed_outcome_serialization_cannot_leak_success_to_another_request(): void
    {
        $this->actingAs($this->actor);
        $firstUrl = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        $this->target = User::factory()->create(['status' => 'aktif']);
        $secondUrl = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        $firstIndex = $this->get($firstUrl)->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($firstUrl, PHP_URL_QUERY), $firstQuery);
        $this->assertTrue(Str::isUuid($firstQuery['receipt'] ?? ''));
        Inertia::share('serializationFault', function () {
            // Fault tepat sesudah outcome dipasang, sebelum adapter menarik dedicated flash.
            if (request()->session()->has(SessionKey::FLASH_DATA.'.roleAssignmentOutcome')) {
                request()->session()->put(SessionKey::FLASH_DATA.'.otherFeature', 'preserved');
                throw new RuntimeException('serialization-unavailable');
            }

            return null;
        });
        try {
            $this->get($firstIndex)
                ->assertStatus(500)->assertSessionMissing(SessionKey::FLASH_DATA.'.roleAssignmentOutcome')
                ->assertSessionHas(SessionKey::FLASH_DATA.'.otherFeature', 'preserved');
        } finally {
            Inertia::share('serializationFault', null);
        }
        $this->get('/akses/peran')->assertInertia(fn (Assert $page) => $page->missingFlash('roleAssignmentOutcome'));
        $this->get($firstIndex)->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missingFlash('roleAssignmentOutcome'));
        $secondIndex = $this->get($secondUrl)->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($secondUrl, PHP_URL_QUERY), $secondQuery);
        $this->get($secondIndex)->assertInertia(fn (Assert $page) => $page->hasFlash('roleAssignmentOutcome', [
            'receipt_id' => $secondQuery['receipt'], 'status' => 'assigned', 'has_active_pj' => false,
        ]));
        $this->assertSame(2, AuditLog::where('tindakan', 'user_roles.tambah')->count());
    }

    public function test_missing_malformed_and_duplicate_references_cannot_consume_another_outcome(): void
    {
        $this->actingAs($this->actor);
        $url = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $reference = $query['receipt'];
        foreach (['status=changed&has_active_pj=true', 'receipt=bad', 'receipt[]='.$reference, 'receipt='.$reference.'&receipt='.$reference] as $invalid) {
            $this->get('/akses/peran/hasil?'.$invalid)->assertInertia(fn (Assert $page) => $page
                ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missing('status')->missingFlash('roleAssignmentOutcome'));
        }
        $this->get('/akses/peran?receipt=bad')->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missingFlash('roleAssignmentOutcome'));
        $index = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($index)->assertInertia(fn (Assert $page) => $page->hasFlash('roleAssignmentOutcome', [
            'receipt_id' => $reference, 'status' => 'assigned', 'has_active_pj' => false,
        ]));
    }

    public function test_session_regeneration_makes_old_receipt_unavailable_without_consuming_it(): void
    {
        $this->actingAs($this->actor);
        $url = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        session()->migrate(true);
        $this->withCookie(config('session.cookie'), session()->getId());
        $index = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($index)->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missingFlash('roleAssignmentOutcome'));
        $this->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->where('confirmationUnavailable', true)->missingFlash('roleAssignmentOutcome'));
        $this->assertDatabaseCount('cache', 1);
    }

    public function test_receipt_is_removed_from_pagination_and_normal_index_discards_only_its_stale_flash(): void
    {
        User::factory()->count(22)->create(['nama' => 'Z Pengguna']);
        $this->actingAs($this->actor);
        $url = $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertStatus(303)->headers->get('Location');
        $index = $this->get($url)->assertRedirect()->headers->get('Location').'&q=Z';
        $this->get($index)->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Z')->where('users.next_page_url', fn ($url) => is_string($url) && ! str_contains($url, 'receipt') && str_contains($url, 'q=Z')));
        Inertia::flash([
            'roleAssignmentOutcome' => ['receipt_id' => (string) Str::uuid(), 'status' => 'assigned', 'has_active_pj' => true],
            'otherFeature' => 'preserved',
        ]);
        session()->save();
        $this->get('/akses/peran?q=Z&page=2')->assertInertia(fn (Assert $page) => $page
            ->missingFlash('roleAssignmentOutcome')->hasFlash('otherFeature', 'preserved'));
    }

    public function test_fresh_noop_preserves_provenance_but_stale_aba_token_is_rejected(): void
    {
        $action = app(AssignRole::class);
        $pimpinan = Role::where('kode', 'pimpinan')->value('id');
        $other = Role::where('kode', 'pegawai')->value('id');
        $action->handle($this->actor, $this->target->id, $pimpinan, 'Awal', null);
        $first = $this->token();
        $this->assertSame(['status' => 'unchanged', 'has_active_pj' => false], $action->handle($this->actor, $this->target->id, $pimpinan, 'Ulang', array_reverse($first, true)));
        $this->assertSame($first, $this->token());
        $this->assertDatabaseCount('audit_log', $this->seedAudits + 1);
        $action->handle($this->actor, $this->target->id, $other, 'Ganti', $first);
        $action->handle($this->actor, $this->target->id, $pimpinan, 'Kembali', $this->token());
        $latest = $this->token();
        try {
            $action->handle($this->actor, $this->target->id, $pimpinan, 'Form lama', $first);
            $this->fail('Token ABA harus ditolak sebelum no-op.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('expected_assignment', $exception->errors());
        }
        $this->assertSame($latest, $this->token());
        $this->assertSame(3, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'user_roles.ditolak')->count());
    }

    public function test_admin_with_both_permissions_can_assign_superadmin_without_role_hierarchy(): void
    {
        $this->actor->roles()->sync([Role::where('kode', 'admin')->value('id') => [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
        ]]);
        $role = Role::where('kode', 'superadmin')->value('id');
        $this->assertSame(['status' => 'assigned', 'has_active_pj' => false], app(AssignRole::class)
            ->handle($this->actor->fresh(), $this->target->id, $role, 'Penugasan oleh admin', null));
        $this->assertDatabaseHas('user_roles', ['user_id' => $this->target->id, 'role_id' => $role]);
        $this->assertSame(1, AuditLog::where('tindakan', 'user_roles.tambah')->count());
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_domain_input_does_not_assign(string $case, string $field): void
    {
        $role = Role::where('kode', 'pimpinan')->sole();
        $reason = 'Perubahan uji';
        if ($case === 'inactive') {
            $role->update(['aktif' => false]);
        } elseif (in_array($case, ['foreign', 'pic'], true)) {
            $role = Role::create(['kode' => $case === 'pic' ? 'pic' : 'asing', 'nama' => 'Peran lama', 'urutan' => 99]);
        } elseif ($case === 'unknown') {
            $role->id = (string) Str::uuid();
        } else {
            $reason = $case === 'long' ? str_repeat('a', 2001) : '   ';
        }
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, $role->id, $reason, null);
            $this->fail('Input tidak sah harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertSame(0, AuditLog::whereIn('tindakan', ['user_roles.tambah', 'user_roles.ubah'])->count());
    }

    public static function invalidChanges(): array
    {
        return [['blank', 'alasan'], ['long', 'alasan'], ['inactive', 'role_id'], ['unknown', 'role_id'], ['foreign', 'role_id'], ['pic', 'role_id']];
    }

    #[DataProvider('requiredPermissions')]
    public function test_action_rechecks_live_deny_even_for_superadmin_and_audits_once(string $permission): void
    {
        $resolver = app(PermissionResolver::class);
        $this->assertTrue($resolver->allows($this->actor, 'pengguna:read'));
        $this->assertTrue($resolver->allows($this->actor, 'akses:update'));
        $this->deny($permission);
        foreach ([$this->target->id, (string) Str::uuid()] as $targetId) {
            try {
                app(AssignRole::class)->handle($this->actor, $targetId, Role::where('kode', 'pimpinan')->value('id'), 'Ditolak', null);
                $this->fail('Izin terbaru harus diperiksa tanpa membocorkan keberadaan target.');
            } catch (AuthorizationException) {
                $this->assertDatabaseMissing('user_roles', ['user_id' => $targetId]);
            }
            $audit = AuditLog::where('sumber', 'manual')->where('objek_id', $targetId)->sole();
            $this->assertSame('user_roles.ditolak', $audit->tindakan);
            $this->assertSame($this->actor->id, $audit->actor_id);
            $this->assertCount(2, $audit->dasar_izin);
            $this->assertFalse($audit->dasar_izin[$permission === 'pengguna:read' ? 'pengguna_read' : 'akses_update']['allowed']);
            $this->assertNull($audit->nilai_lama);
            $this->assertNull($audit->nilai_baru);
        }
        $this->assertSame(2, AuditLog::where('sumber', 'manual')->count());
    }

    public static function requiredPermissions(): array
    {
        return [['pengguna:read'], ['akses:update']];
    }

    public function test_failed_audit_rolls_back_assignment(): void
    {
        $this->mock(WriteAuditLog::class)->shouldReceive('handle')->andThrow(new RuntimeException('audit-unavailable'));
        try {
            app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pimpinan')->value('id'), 'Rollback', null);
            $this->fail('Audit failure harus membatalkan mutasi.');
        } catch (RuntimeException $exception) {
            $this->assertSame('audit-unavailable', $exception->getMessage());
        }
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', $this->seedAudits + 0);
    }

    #[DataProvider('requiredPermissions')]
    public function test_http_gate_rejects_missing_permission_and_deny_without_audit(string $permission): void
    {
        $permissionId = Permission::where('kode', $permission)->value('id');
        $row = DB::table('role_permissions')->where('role_id', Role::where('kode', 'superadmin')->value('id'))->where('permission_id', $permissionId)->sole();
        DB::table('role_permissions')->where('id', $row->id)->delete();
        $this->actingAs($this->actor);
        foreach (['missing', 'denied'] as $case) {
            if ($case === 'denied') {
                DB::table('role_permissions')->insert((array) $row);
                $this->deny($permission);
            }
            $this->get('/akses/peran')->assertForbidden();
            $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertForbidden();
            $this->assertDatabaseCount('audit_log', $this->seedAudits + 0);
            $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        }
    }

    public function test_index_bounds_users_filters_and_orders_official_roles_without_sensitive_props(): void
    {
        $this->target->update(['nama' => 'A Target', 'email' => 'target@example.test']);
        foreach (['superadmin', 'admin', 'perencanaan', 'pimpinan', 'pegawai'] as $code) {
            $user = User::factory()->create(['nama' => 'Z '.$code, 'status' => 'aktif']);
            $user->roles()->attach(Role::where('kode', $code)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        User::factory()->count(15)->create(['nama' => 'Z Pengguna']);
        $this->actingAs($this->actor)->get('/akses/peran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->has('users.data', 20)->where('users.total', 22)
            ->where('users.data.0.id', $this->target->id)->where('users.data.0.assignment', null)
            ->where('roles.0.kode', 'superadmin')->where('roles.1.kode', 'admin')->where('roles.2.kode', 'perencanaan')
            ->where('roles.3.kode', 'pimpinan')->where('roles.4.kode', 'pegawai')
            ->missing('users.data.0.keycloak_id')->missing('users.data.0.roles')->missing('users.data.0.role_permissions')
            ->where('auth.can.assignRole', true)->where('can.assignRole', true));
        $this->get('/akses/peran?q=target%40example.test')->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.id', $this->target->id));
        $this->get('/akses/peran?q=A%20Target')->assertInertia(fn (Assert $page) => $page->has('users.data', 1));
        $this->get('/akses/peran?page=2')->assertInertia(fn (Assert $page) => $page->has('users.data', 2));
        $this->get('/akses/peran?page=0')->assertSessionHasErrors('page');
        $this->get('/akses/peran?q='.str_repeat('a', 101))->assertSessionHasErrors('q');
        app(AssignRole::class)->handle($this->actor, $this->target->id, Role::where('kode', 'pimpinan')->value('id'), 'Penugasan', null);
        Role::where('kode', 'pimpinan')->update(['aktif' => false]);
        $this->get('/akses/peran?q=target%40example.test')->assertInertia(fn (Assert $page) => $page
            ->has('roles', 4)->where('roles.3.kode', 'pegawai')
            ->where('users.data.0.current_role.kode', 'pimpinan')->where('users.data.0.current_role.aktif', false));
        $this->post('/akses/peran/'.$this->target->id, $this->payload())->assertSessionHasErrors('role_id');
    }

    #[DataProvider('invalidPayloads')]
    public function test_http_rejects_invalid_payload_without_mutation(array $override, array $remove, string $field): void
    {
        $payload = array_replace($this->payload(), $override);
        foreach ($remove as $key) {
            unset($payload[$key]);
        }
        $this->actingAs($this->actor)->from('/akses/peran')->post('/akses/peran/'.$this->target->id, $payload)
            ->assertRedirect('/akses/peran')->assertSessionHasErrors($field);
        $this->assertDatabaseMissing('user_roles', ['user_id' => $this->target->id]);
        $this->assertDatabaseCount('audit_log', $this->seedAudits + 0);
    }

    public static function invalidPayloads(): array
    {
        return [
            [['alasan' => '   '], [], 'alasan'],
            [[], ['expected_assignment'], 'expected_assignment'],
            [['expected_assignment' => ['id' => 'bad']], [], 'expected_assignment'],
            [['actor_id' => 'spoofed'], [], 'actor_id'],
            [['expected_assignment' => ['id' => 'bad', 'role_id' => 'bad', 'audit_id' => null, 'extra' => 'bad']], [], 'expected_assignment'],
        ];
    }

    public function test_self_change_receipt_remains_available_after_losing_access_and_expires(): void
    {
        $this->target = $this->actor;
        $this->assignment($this->indicator(), $this->actor, '2026-01-01', '2026-01-01 09:00:00');
        $url = $this->actingAs($this->actor)->from('/akses/peran')->post('/akses/peran/'.$this->actor->id, $this->payload())
            ->assertStatus(303)->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentResult', false)->where('canReturn', false)
            ->hasFlash('roleAssignmentOutcome', ['receipt_id' => $query['receipt'], 'status' => 'changed', 'has_active_pj' => true])
            ->where('auth.can.assignRole', false)->missing('status')->missing('users')->missing('target')->missing('keycloak_id'));
        $this->assertSame([], array_values(array_filter(DB::getQueryLog(), fn (array $query) => str_contains($query['query'], 'penanggung_jawab'))));
        DB::disableQueryLog();
        $this->get('/akses/peran')->assertForbidden();
        $this->get($url)->assertInertia(fn (Assert $page) => $page->missingFlash('roleAssignmentOutcome'));
        $this->get('/akses/peran/hasil')->assertInertia(fn (Assert $page) => $page->missingFlash('roleAssignmentOutcome'));
    }

    public function test_http_assigns_first_role_and_protects_guest_pending_and_unknown_target(): void
    {
        $this->get('/akses/peran')->assertRedirect('/login');
        $this->actingAs($this->target)->get('/akses/peran')->assertRedirect('/auth/pending');
        $this->actingAs($this->actor)->post('/akses/peran/not-uuid', $this->payload())->assertNotFound();
        $this->post('/akses/peran/'.Str::uuid(), $this->payload())->assertNotFound();
        $payload = $this->payload();
        $payload['role_id'] = strtoupper($payload['role_id']);
        $url = $this->post('/akses/peran/'.$this->target->id, $payload)->assertStatus(303)->headers->get('Location');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $index = $this->get($url)->assertRedirect()->headers->get('Location');
        $this->get($index)->assertInertia(fn (Assert $page) => $page
            ->component('Access/RoleAssignmentIndex', false)->hasFlash('roleAssignmentOutcome', [
                'receipt_id' => $query['receipt'], 'status' => 'assigned', 'has_active_pj' => false,
            ]));
        $this->get('/akses/peran')->assertInertia(fn (Assert $page) => $page->missingFlash('roleAssignmentOutcome'));
        $this->assertSame('pimpinan', $this->target->roles()->value('kode'));
    }

    private function payload(): array
    {
        return ['role_id' => Role::where('kode', 'pimpinan')->value('id'), 'alasan' => 'Penugasan melalui form', 'expected_assignment' => $this->token()];
    }

    private function indicator(): IndikatorKinerja
    {
        $unit = Unit::create(['nama' => 'Unit PJ Uji', 'created_by' => $this->actor->id]);
        $renstra = Renstra::create(['kode' => 'R-PJ', 'nama' => 'Renstra PJ Uji', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $this->actor->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-PJ', 'deskripsi' => 'Sasaran PJ Uji']);

        return IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-PJ',
            'nama' => 'Indikator PJ Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id,
            'created_by_role' => $this->actor->roles->first()?->kode ?? 'superadmin',
        ]);
    }

    private function assignment(IndikatorKinerja $indicator, User $user, string $date, string $created): void
    {
        PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $user->id, 'tanggal_mulai_berlaku' => $date, 'ditetapkan_oleh' => $this->actor->id, 'created_at' => $created]);
    }

    private function token(): ?array
    {
        $row = DB::table('user_roles')->where('user_id', $this->target->id)->first(['id', 'role_id', 'audit_id']);

        return $row ? (array) $row : null;
    }

    private function deny(string $permission): void
    {
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => null, 'alasan' => 'Pembatasan uji', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }
}
