<?php

namespace Tests\Feature\IndikatorKomponen;

use App\Actions\Perencanaan\ReadIndicatorEditor;
use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionDecision;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SubmitsIndicatorDefinition;
use Tests\TestCase;

class FormulaMutationRegressionTest extends TestCase
{
    use RefreshDatabase;
    use SubmitsIndicatorDefinition;

    private User $actor;

    private IndikatorKinerja $indikator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['status' => 'aktif']);
        $this->actor->roles()->attach(Role::where('kode', 'perencanaan')->firstOrFail()->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
        $renstra = Renstra::create([
            'kode' => 'RENSTRA-FORMULA', 'nama' => 'Renstra Formula',
            'tahun_mulai' => 2025, 'tahun_selesai' => 2029,
            'created_by' => $this->actor->id,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id, 'kode' => 'SS-FORMULA',
            'deskripsi' => 'Sasaran Formula', 'urutan' => 1,
        ]);
        $unit = Unit::create([
            'nama' => 'Unit Formula', 'status' => 'aktif', 'created_by' => $this->actor->id,
        ]);
        $this->indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id,
            'kode' => 'IKU-FORMULA', 'nama' => 'Indikator Formula', 'satuan' => '%',
            'tipe_perhitungan' => 'rasio_persen', 'arah' => 'naik_baik',
            'presisi' => 2, 'desimal_tampilan' => 2,
            'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
        $this->buatKomponen('n', 'pembilang');
        $this->buatKomponen('t', 'penyebut');
    }

    private function buatKomponen(string $kode, string $peran): IndikatorKomponen
    {
        return IndikatorKomponen::create([
            'indikator_id' => $this->indikator->id, 'kode' => $kode,
            'label' => 'Komponen '.$kode, 'peran' => $peran, 'bobot' => '1',
            'urutan' => $kode === 't' ? 2 : 1, 'aktif' => true,
            'created_by' => $this->actor->id,
        ]);
    }

    private function token(): string
    {
        return $this->indikator->fresh()->updated_at->toISOString();
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        return [
            'parent' => $this->indikator->fresh()->getAttributes(),
            'children' => $this->indikator->komponen()->orderBy('id')->get()->map->getAttributes()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(IndikatorKomponen $component): array
    {
        return [
            'kode' => $component->kode, 'label' => $component->label,
            'peran' => $component->peran, 'bobot' => (string) $component->bobot,
            'urutan' => $component->urutan, 'aktif' => $component->aktif,
            'alasan' => 'Perubahan definisi formula yang terkontrol.',
            'expected_updated_at' => $this->token(),
        ];
    }

    private function definitionItem(IndikatorKomponen $row): array
    {
        return $row->only(['id', 'kode', 'label', 'satuan', 'peran', 'bobot', 'urutan', 'aktif']);
    }

    public static function invalidMutations(): array
    {
        return [
            'rasio tambah penjumlah' => ['rasio_persen', 'post', 'penjumlah'],
            'rasio tambah penyebut kedua' => ['rasio_persen', 'post', 'penyebut'],
            'rasio ubah penyebut ke pembilang' => ['rasio_persen', 'put', 'pembilang'],
            'rasio nonaktif penyebut terakhir' => ['rasio_persen', 'put', 'inactive'],
            'rasio hapus penyebut terakhir' => ['rasio_persen', 'delete', 'penyebut'],
            'penjumlahan nonaktif penjumlah terakhir' => ['penjumlahan', 'put', 'inactive'],
            'penjumlahan hapus penjumlah terakhir' => ['penjumlahan', 'delete', 'penjumlah'],
            'penjumlahan tambah pembilang' => ['penjumlahan', 'post', 'pembilang'],
        ];
    }

    #[DataProvider('invalidMutations')]
    public function test_candidate_invalid_ditolak_tanpa_mutasi_atau_audit_sukses(string $type, string $method, string $change): void
    {
        if ($type === 'penjumlahan') {
            $this->indikator->komponen()->delete();
            $this->indikator->update(['tipe_perhitungan' => $type]);
            $target = $this->buatKomponen('j', 'penjumlah');
        } else {
            $target = $this->indikator->komponen()->where('kode', 't')->firstOrFail();
        }
        $payload = $this->payload($target);
        if ($method === 'post') {
            $payload = array_merge($payload, ['kode' => 'baru', 'peran' => $change]);
        } else {
            if ($change === 'inactive') {
                $payload['aktif'] = false;
            } elseif ($method === 'put') {
                $payload['peran'] = $change;
            }
        }
        $before = $this->state();
        $auditCount = AuditLog::count();
        $this->actingAs($this->actor)->submitKomponen($method, $this->indikator->id, $method === 'post' ? null : $target->id, $payload, true)
            ->assertUnprocessable()->assertJsonValidationErrors('tipe_perhitungan');
        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());
    }

    public static function mutationMethods(): array
    {
        return ['create' => ['post', 'create'], 'update' => ['put', 'update'], 'delete' => ['delete', 'delete']];
    }

    #[DataProvider('mutationMethods')]
    public function test_mutasi_valid_bump_parent_dan_token_tab_lama_ditolak(string $method, string $permission): void
    {
        $target = $this->buatKomponen('tambahan', 'pembilang');
        $oldToken = $this->token();
        $this->travelTo($this->indikator->fresh()->updated_at);
        $payload = $this->payload($target);
        if ($method === 'post') {
            $payload['kode'] = 'baru';
        } else {
            $payload['label'] = 'Label yang diperbarui';
        }
        $this->actingAs($this->actor)->submitKomponen($method, $this->indikator->id, $method === 'post' ? null : $target->id, $payload, true)->assertRedirect();
        $this->assertTrue($this->indikator->fresh()->updated_at->gt($oldToken));
        $after = $this->state();
        $auditCount = AuditLog::count();
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'manual', 'komponen' => [], 'expected_updated_at' => $oldToken,
            'alasan' => 'Uji konflik token tab lama pasca mutasi komponen.',
        ])->assertConflict()->assertJsonValidationErrors('konflik');
        $this->assertSame($after, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());
    }

    #[DataProvider('mutationMethods')]
    public function test_allow_awal_dan_deny_di_transaksi_menghentikan_mutasi(string $method, string $permission): void
    {
        $target = $this->buatKomponen('tambahan', 'pembilang');
        $calls = 0;
        $mock = \Mockery::mock(PermissionResolver::class)->makePartial();
        $this->assertTrue(app(PermissionResolver::class)->allows($this->actor, 'komponen:'.$permission));
        $mock->shouldReceive('allows')->withArgs(fn ($user, $code, $unit = null) => $code === 'komponen:'.$permission)
            ->andReturnUsing(fn ($user, $code) => $mock->resolve($user, $code)->allowed);
        $mock->shouldReceive('resolve')->withArgs(fn ($user, $code, $unit = null) => $code === 'komponen:'.$permission)
            ->andReturnUsing(function ($user, $code) use (&$calls) {
                $calls++;
                $this->assertGreaterThan(1, DB::transactionLevel(), 'Re-auth wajib berada dalam transaksi mutation.');

                return new PermissionDecision(false, $code, ['alasan' => 'revoked_inside_transaction', 'sumber_allow' => ['roles' => [], 'grants' => []], 'deny' => []]);
            });
        $this->app->instance(PermissionResolver::class, $mock);
        $payload = $this->payload($target);
        if ($method === 'post') {
            $payload['kode'] = 'baru';
        } else {
            $payload['label'] = 'Tidak boleh tersimpan';
        }
        $before = $this->state();
        $this->actingAs($this->actor)->submitKomponen($method, $this->indikator->id, $method === 'post' ? null : $target->id, $payload, true)->assertForbidden();
        $this->assertSame(1, $calls);
        $this->assertSame($before, $this->state());
        $event = ['create' => 'buat', 'update' => 'ubah', 'delete' => 'hapus'][$permission];
        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('revoked_inside_transaction', $audit->dasar_izin['alasan']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.'.$event]);
    }

    public function test_formula_existing_same_type_menerima_final_set_dan_bump_versi(): void
    {
        $items = $this->indikator->komponen()->get();
        $oldToken = $this->token();
        $this->travelTo($this->indikator->fresh()->updated_at);
        $payload = $items->map(fn ($item) => array_merge($this->definitionItem($item), ['id' => $item->id]))->all();
        $payload[0]['label'] = 'Pembilang hasil edit atomik';
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $oldToken,
            'alasan' => 'Edit atomik tipe sama dengan identitas dipertahankan.',
        ])->assertRedirect();
        $this->assertSame($items->pluck('id')->sort()->values()->all(), $this->indikator->komponen()->pluck('id')->sort()->values()->all());
        $this->assertTrue($this->indikator->fresh()->updated_at->gt($oldToken));
        $this->assertDatabaseHas('indikator_komponen', ['id' => $items[0]->id, 'label' => 'Pembilang hasil edit atomik']);
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'komponen.ubah', 'objek_id' => $items[0]->id]);
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $oldToken,
            'alasan' => 'Kontrol token usang pasca edit atomik tipe sama.',
        ])->assertConflict();
    }

    public function test_formula_ke_manual_menonaktifkan_child_tanpa_menghapus_identitas(): void
    {
        $ids = $this->indikator->komponen()->pluck('id')->sort()->values()->all();
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'manual', 'komponen' => $this->indikator->komponen->map(fn ($row) => array_merge($row->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'satuan']), ['aktif' => false]))->all(), 'expected_updated_at' => $this->token(),
            'alasan' => 'Menonaktifkan komponen saat beralih ke target manual.',
        ])->assertRedirect();
        $this->assertSame('manual', $this->indikator->fresh()->tipe_perhitungan);
        $this->assertSame(0, $this->indikator->komponen()->where('aktif', true)->count());
        $this->assertSame($ids, $this->indikator->komponen()->pluck('id')->sort()->values()->all());
        $this->assertSame(2, AuditLog::where('tindakan', 'komponen.ubah')->count());
        $props = app(ReadIndicatorEditor::class)->handle($this->actor, $this->indikator->id, true);
        $items = collect($props['komponen']);
        $this->assertSame($ids, $items->pluck('id')->sort()->values()->all());
        $payload = $items->map(fn ($item) => array_merge($item, ['aktif' => true]))->all();
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $this->token(),
            'alasan' => 'Mengaktifkan kembali komponen pasca target manual.',
        ])->assertRedirect();
        $this->assertSame($ids, $this->indikator->komponen()->where('aktif', true)->pluck('id')->sort()->values()->all());
    }

    public function test_formula_final_dapat_menukar_kode_existing_tanpa_mengubah_id(): void
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $t = $this->indikator->komponen()->where('kode', 't')->firstOrFail();
        $payload = [
            array_merge($this->definitionItem($n), ['id' => $n->id, 'kode' => 't']),
            array_merge($this->definitionItem($t), ['id' => $t->id, 'kode' => 'n']),
        ];
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $this->token(),
            'alasan' => 'Menukar kode antar komponen existing tanpa mengubah identitas.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('indikator_komponen', ['id' => $n->id, 'kode' => 't', 'peran' => 'pembilang']);
        $this->assertDatabaseHas('indikator_komponen', ['id' => $t->id, 'kode' => 'n', 'peran' => 'penyebut']);
        $this->assertSame(2, AuditLog::where('tindakan', 'komponen.ubah')->count());
    }

    public function test_formula_stale_dengan_id_child_terhapus_ditolak_sebagai_konflik(): void
    {
        $target = $this->buatKomponen('tambahan', 'pembilang');
        $oldToken = $this->token();
        $payload = $this->indikator->komponen()->get()->map(fn ($item) => array_merge($this->definitionItem($item), ['id' => $item->id]))->all();
        $this->actingAs($this->actor)->deleteKomponenJson($this->indikator->id, $target->id, [
            'alasan' => 'Pembilang tambahan dihapus dengan formula tetap lengkap.',
            'expected_updated_at' => $oldToken,
        ])->assertRedirect();
        $after = $this->state();
        $auditCount = AuditLog::count();
        $this->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $oldToken,
            'alasan' => 'Kontrol konflik token usang dengan child terhapus.',
        ])->assertConflict()->assertJsonValidationErrors('konflik');
        $this->assertSame($after, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());
    }

    public function test_formula_token_terkini_tetap_menolak_id_di_luar_indikator(): void
    {
        $payload = $this->indikator->komponen()->get()->map(fn ($item) => array_merge($this->definitionItem($item), ['id' => $item->id]))->all();
        $payload[0]['id'] = (string) Str::uuid();
        $before = $this->state();
        $auditCount = AuditLog::count();
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'rasio_persen', 'komponen' => $payload, 'expected_updated_at' => $this->token(),
            'alasan' => 'Kontrol penolakan identitas komponen di luar indikator.',
        ])->assertUnprocessable()->assertJsonValidationErrors('komponen.0.id');
        $this->assertSame($before, $this->state());
        $this->assertSame($auditCount + 1, AuditLog::count());
    }

    public static function unsafeReasons(): array
    {
        return [
            'create NUL' => ['post', 'create', "Alasan\0penolakan"],
            'create kontrol saja' => ['post', 'create', str_repeat("\x01", 15)],
            'update invalid UTF8' => ['put', 'update', "Rusak\xFFencoding"],
            'delete panjang' => ['delete', 'delete', str_repeat('é', 1100)],
        ];
    }

    #[DataProvider('unsafeReasons')]
    public function test_denied_reason_aman_dan_tetap_403(string $method, string $permission, string $reason): void
    {
        $pegawai = User::factory()->create(['status' => 'aktif']);
        $pegawai->roles()->attach(Role::where('kode', 'pegawai')->firstOrFail()->id, [
            'id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
        UserPermissionDeny::create(['user_id' => $pegawai->id, 'permission_id' => Permission::where('kode', 'komponen:read')->value('id'),
            'ditetapkan_oleh' => $this->actor->id, 'alasan' => 'Deny baca pada boundary awal.']);
        $target = $this->indikator->komponen()->firstOrFail();
        $before = $this->state();
        $this->actingAs($pegawai)->submitKomponen($method, $this->indikator->id, $method === 'post' ? null : $target->id, ['alasan' => $reason])->assertForbidden();
        $this->assertSame($before, $this->state());
        $audit = AuditLog::where('actor_id', $pegawai->id)->firstOrFail();
        $this->assertTrue(mb_check_encoding($audit->alasan, 'UTF-8'));
        $this->assertStringNotContainsString("\0", $audit->alasan);
        $this->assertLessThanOrEqual(1000, mb_strlen($audit->alasan));
        $this->assertSame('komponen:read', $audit->dasar_izin['permission']);
    }

    public function test_success_reason_disanitasi_di_boundary_audit_dan_valid_reason_utuh(): void
    {
        $target = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        foreach ([
            "Alasan\0resmi perubahan" => 'Alasanresmi perubahan',
            'Alasan valid tetap utuh.' => 'Alasan valid tetap utuh.',
        ] as $raw => $expected) {
            $this->actingAs($this->actor)->updateKomponen($this->indikator->id, $target->id, array_merge($this->payload($target->fresh()), [
                'label' => $expected, 'alasan' => $raw,
            ]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('audit_log', ['tindakan' => 'komponen.ubah', 'objek_id' => $target->id, 'alasan' => $expected]);
        }
    }

    public static function actualRevocations(): array
    {
        $cases = [];
        foreach (['post' => 'create', 'put' => 'update', 'delete' => 'delete'] as $method => $permission) {
            foreach (['deny', 'inactive'] as $change) {
                $cases[$permission.' '.$change] = [$method, $permission, $change];
            }
        }

        return $cases;
    }

    #[DataProvider('actualRevocations')]
    public function test_state_acl_aktual_berubah_setelah_gate_dan_diperiksa_ulang(string $method, string $permission, string $change): void
    {
        $target = $this->buatKomponen('tambahan', 'pembilang');
        $before = $this->state();
        $real = app(PermissionResolver::class);
        $initialCalls = 0;
        $mock = \Mockery::mock(PermissionResolver::class)->makePartial();
        $mock->shouldReceive('resolve')->withArgs(fn ($user, $code, $unit = null) => $code === 'komponen:read')
            ->andReturnUsing(function ($user, $code) use ($real, $change, $permission, &$initialCalls) {
                $decision = $real->resolve($user, $code);
                if ($initialCalls++ === 0) {
                    $this->assertTrue($decision->allowed);
                    if ($change === 'inactive') {
                        User::whereKey($user->id)->update(['status' => 'nonaktif']);
                    } else {
                        $this->deny('komponen:'.$permission);
                    }
                }

                return $decision;
            });
        $this->app->instance(PermissionResolver::class, $mock);
        $this->actingAs($this->actor)->submitKomponen($method, $this->indikator->id, $method === 'post' ? null : $target->id, array_merge($this->payload($target), [
            'kode' => $method === 'post' ? 'baru' : $target->kode,
            'label' => 'Delta update yang sungguh berbeda',
        ]))->assertForbidden();
        $this->assertGreaterThanOrEqual(2, $initialCalls);
        $this->assertSame($before, $this->state());
        $event = ['create' => 'buat', 'update' => 'ubah', 'delete' => 'hapus'][$permission];
        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame($change === 'inactive' ? 'inactive_user' : 'explicit_deny', $audit->dasar_izin['alasan']);
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'komponen.'.$event]);
    }

    private function deny(string $permission): void
    {
        UserPermissionDeny::create([
            'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', $permission)->firstOrFail()->id,
            'unit_id' => null, 'alasan' => 'Penolakan eksplisit untuk regression.',
            'ditetapkan_oleh' => $this->actor->id,
        ]);
    }

    public function test_formula_tidak_memakai_create_sebagai_bypass_update_existing(): void
    {
        $this->deny('komponen:update');
        $before = $this->state();
        $auditCount = AuditLog::count();
        $this->actingAs($this->actor)->patchJson("/perencanaan/indikator/{$this->indikator->id}/formula", [
            'tipe_perhitungan' => 'manual', 'komponen' => $this->indikator->komponen->map(fn ($row) => array_merge($row->only(['id', 'kode', 'label', 'peran', 'bobot', 'urutan', 'satuan']), ['aktif' => false]))->all(), 'expected_updated_at' => $this->token(),
            'alasan' => 'Kontrol bypass update existing via final-set manual.',
        ])->assertForbidden();
        $this->assertSame($before, $this->state());
        $audit = AuditLog::where('tindakan', 'indikator.ubah_ditolak')->firstOrFail();
        $this->assertSame('komponen:update', $audit->dasar_izin['permission']);
        $this->assertSame('ditolak', $audit->dasar_izin['keputusan']);
        $this->assertSame($auditCount + 1, AuditLog::count());
    }

    public function test_props_formula_exact_dan_explicit_deny_read_menyembunyikan_definisi(): void
    {
        $n = $this->indikator->komponen()->where('kode', 'n')->firstOrFail();
        $n->update(['bobot' => '123456789.123456789012']);
        $renstraId = $this->indikator->sasaranStrategis->renstra_id;
        $action = app(ReadIndicatorEditor::class);
        $props = $action->handle($this->actor, $this->indikator->id, true);
        $items = collect($props['komponen']);
        $this->assertSame('123456789.123456789012', $items->firstWhere('id', $n->id)['bobot']);
        $this->deny('komponen:read');
        $denied = $action->handle($this->actor, $this->indikator->id, true);
        $this->assertNull($denied['komponen']);
        $this->assertNull($denied['formulaContract']);
    }
}
