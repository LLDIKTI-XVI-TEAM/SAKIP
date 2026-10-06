<?php

namespace Tests\Feature\TargetTahunan;

use App\Actions\Perencanaan\IndexSasaranIndikator;
use App\Actions\TargetTahunan\ShowTargetTahunan;
use App\Http\Requests\TargetTahunan\SaveTargetTahunanRequest;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\TargetKinerja;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionResolver;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanUiIntegrationTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    /** Ubah ACL setelah keputusan request nyata, sebelum Laravel memproses hasilnya. */
    private function changeAclAfterAuthorization(Closure $change): void
    {
        $this->app->bind(SaveTargetTahunanRequest::class, function () use ($change) {
            $request = new class extends SaveTargetTahunanRequest
            {
                public Closure $change;

                public function authorize(): bool
                {
                    $allowed = parent::authorize();
                    ($this->change)();

                    return $allowed;
                }
            };
            $request->change = $change;

            return $request;
        });
    }

    #[DataProvider('denyChanges')]
    public function test_rejection_audit_keeps_the_initial_deny_when_acl_changes(bool $replace): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $deny = UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'target:update')->sole()->id,
            'alasan' => 'Deny awal', 'ditetapkan_oleh' => $actor->id]);
        $expected = app(PermissionResolver::class)->resolve($actor, 'target:update')->toAuditBasis();
        $this->changeAclAfterAuthorization(function () use ($deny, $replace): void {
            $deny->delete();
            if ($replace) {
                $deny->replicate()->fill(['alasan' => 'Deny pengganti'])->save();
            }
        });

        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [])->assertForbidden();

        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole();
        $this->assertSame('izin_ditolak', $audit->nilai_baru['alasan_penolakan']);
        $this->assertEquals($expected, $audit->dasar_izin['target:update']);
        $this->assertSame([$deny->id], $audit->dasar_izin['target:update']['deny']);
        $this->assertSame('diizinkan', $audit->dasar_izin['indikator:read']['keputusan']);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public static function denyChanges(): array
    {
        return ['deny dicabut' => [false], 'deny diganti' => [true]];
    }

    public function test_validation_audit_keeps_initial_allow_after_acl_changes(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $expected = app(PermissionResolver::class)->resolve($actor, 'target:update')->toAuditBasis();
        $this->changeAclAfterAuthorization(function () use ($actor): void {
            UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'target:update')->sole()->id,
                'alasan' => 'Deny setelah otorisasi', 'ditetapkan_oleh' => $actor->id]);
        });

        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [])->assertUnprocessable();

        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole();
        $this->assertSame('input_tidak_valid', $audit->nilai_baru['alasan_penolakan']);
        $this->assertEquals($expected, $audit->dasar_izin['target:update']);
        $this->assertSame('diizinkan', $audit->dasar_izin['indikator:read']['keputusan']);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_action_rechecks_authorization_after_initial_http_allow(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2026);
        $this->changeAclAfterAuthorization(function () use ($actor): void {
            UserPermissionDeny::create(['user_id' => $actor->id, 'permission_id' => Permission::where('kode', 'target:update')->sole()->id,
                'alasan' => 'Deny sebelum Action', 'ditetapkan_oleh' => $actor->id]);
        });

        $this->actingAs($actor)->putJson('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [
            'baseline' => null, 'target_tahunan' => '76.25', 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid(),
        ])->assertForbidden();

        $audit = AuditLog::where('tindakan', 'target_tahunan.simpan_ditolak')->sole();
        $this->assertSame('ditolak', $audit->dasar_izin['target:update']['keputusan']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['target:update']['alasan']);
        $this->assertSame([UserPermissionDeny::sole()->id], $audit->dasar_izin['target:update']['deny']);
        $this->assertSame(0, TargetKinerja::count());
        $this->assertSame(0, AuditLog::where('tindakan', 'target_tahunan.simpan')->count());
    }

    public function test_redirect_keeps_the_selected_renstra_filter(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2026);
        $origin = route('perencanaan.sasaran-indikator.index', ['renstra_id' => $editor['renstra']['id']]);
        $this->actingAs($actor)->from($origin)->withHeader('X-Inertia', 'true')
            ->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [
                'baseline' => null, 'target_tahunan' => null, 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid(),
            ])->assertRedirect($origin);
    }

    public function test_redirect_does_not_trust_an_external_origin_or_add_unrelated_query(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $editor = app(ShowTargetTahunan::class)->handle($actor, $indicator->id, 2026);
        $this->actingAs($actor)->from('https://untrusted.example/perencanaan/sasaran-indikator?renstra_id='.$editor['renstra']['id'].'&other=1')->withHeader('X-Inertia', 'true')
            ->put('/perencanaan/indikator/'.$indicator->id.'/target-tahunan/2026', [
                'baseline' => null, 'target_tahunan' => null, 'expected_state' => $editor['expected_state'], 'operation_id' => (string) Str::uuid(),
            ])->assertRedirect(route('perencanaan.sasaran-indikator.index'));
    }

    public function test_indicator_row_contains_start_year_for_a_valid_initial_editor_year(): void
    {
        $actor = $this->calendarActor(['indikator:read', 'target:update']);
        $indicator = $this->targetIndicator($actor);
        $indicator->update(['tahun_mulai_berlaku' => 2027]);
        $props = app(IndexSasaranIndikator::class)->handle($actor, $indicator->sasaranStrategis->renstra_id, true);
        $row = collect($props['sasarans'])->flatMap(fn ($sasaran) => $sasaran['indikator_kinerjas'])->firstWhere('id', $indicator->id);
        $this->assertArrayHasKey('tahun_mulai_berlaku', $row);
        $this->assertSame(2027, $row['tahun_mulai_berlaku']);
        $this->actingAs($actor)->getJson('/perencanaan/indikator/'.$indicator->id.'/editor')
            ->assertOk()->assertJsonPath('indikator.tahun_mulai_berlaku', 2027);
    }
}
