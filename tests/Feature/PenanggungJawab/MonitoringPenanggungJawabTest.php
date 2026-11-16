<?php

namespace Tests\Feature\PenanggungJawab;

use App\Actions\PenanggungJawab\MonitorPenanggungJawab;
use App\Models\IndikatorKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Tabel Monitoring Penanggung Jawab (Q68): seluruh PJ efektif, lima filter, paginasi 20. */
class MonitoringPenanggungJawabTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private User $target;

    private Unit $unitA;

    private Unit $unitB;

    private SasaranStrategis $sasaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->actor = User::factory()->create(['nama' => 'Perencana Uji', 'status' => 'aktif']);
        $this->target = User::factory()->create(['nama' => 'Siti Aminah', 'status' => 'aktif']);
        $this->attachRole($this->actor, 'perencanaan');
        $this->unitA = Unit::create(['nama' => 'Bagian Umum', 'created_by' => $this->actor->id]);
        $this->unitB = Unit::create(['nama' => 'Bagian Kelembagaan', 'created_by' => $this->actor->id]);
        $renstra = Renstra::create(['kode' => 'R-MON', 'nama' => 'Renstra Monitoring', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'status' => 'aktif', 'created_by' => $this->actor->id]);
        $this->sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-MON', 'deskripsi' => 'Sasaran Monitoring']);
    }

    public function test_tabel_menampilkan_pj_izin_lengkap_dan_kurang_terurut_kode(): void
    {
        $lengkap = $this->indicator('IKU 1.10', $this->unitA);
        $kurang = $this->indicator('IKU 1.2', $this->unitA);
        $this->assign($lengkap, $this->actor);
        $this->assign($kurang, $this->target);
        $kurang->update(['status' => 'arsip']);

        $this->actingAs($this->actor)->get('/penanggung-jawab?tanggal_acuan=2026-03-15')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('PenanggungJawab/Index')
                ->has('assignments.data', 2)->where('assignments.total', 2)
                ->where('assignments.data.0.indicator.id', $kurang->id)
                ->where('assignments.data.0.readiness.complete', false)->has('assignments.data.0.readiness.missing', 7)
                ->where('assignments.data.0.blocked_reason', fn ($reason) => is_string($reason) && $reason !== '')
                ->where('assignments.data.1.indicator.id', $lengkap->id)
                ->where('assignments.data.1.pic.id', $this->actor->id)
                ->where('assignments.data.1.readiness.complete', true)->has('assignments.data.1.readiness.missing', 0)
                ->where('filters', ['q' => '', 'tanggal_acuan' => '2026-03-15', 'unit_id' => null, 'indikator_id' => null, 'izin' => null]));
    }

    public function test_filter_izin_kerja_memisahkan_lengkap_dan_kurang(): void
    {
        $lengkap = $this->indicator('IKU 1.1', $this->unitA);
        $kurang = $this->indicator('IKU 1.2', $this->unitA);
        $this->assign($lengkap, $this->actor);
        $this->assign($kurang, $this->target);
        $this->actingAs($this->actor);

        $this->get('/penanggung-jawab?izin=kurang')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.indicator.id', $kurang->id)->where('filters.izin', 'kurang'));
        $this->get('/penanggung-jawab?izin=lengkap')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.indicator.id', $lengkap->id));
        $this->get('/penanggung-jawab')->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments.data', 2)->where('assignments.total', 2));
        $this->getJson('/penanggung-jawab?izin=semua')->assertUnprocessable()->assertJsonValidationErrors('izin');
        $this->getJson('/penanggung-jawab?indikator_id=bukan-uuid')->assertUnprocessable()->assertJsonValidationErrors('indikator_id');
    }

    public function test_filter_unit_dan_indikator_dengan_pilihan_dari_penugasan_efektif(): void
    {
        $a1 = $this->indicator('IKU 1.1', $this->unitA);
        $a2 = $this->indicator('IKU 1.2', $this->unitA);
        $b1 = $this->indicator('IKU 2.1', $this->unitB);
        $tanpaPj = $this->indicator('IKU 9.9', $this->unitB);
        $this->assign($a1, $this->actor);
        $this->assign($a2, $this->target);
        $this->assign($b1, $this->target);
        $this->actingAs($this->actor);

        $this->get('/penanggung-jawab?unit_id='.$this->unitB->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.indicator.id', $b1->id)->where('filters.unit_id', $this->unitB->id)
            ->where('pilihan.unit', [['id' => $this->unitB->id, 'nama' => 'Bagian Kelembagaan'], ['id' => $this->unitA->id, 'nama' => 'Bagian Umum']])
            ->where('pilihan.indikator', [
                ['id' => $a1->id, 'kode' => 'IKU 1.1', 'nama' => 'Indikator IKU 1.1', 'unit_id' => $this->unitA->id],
                ['id' => $a2->id, 'kode' => 'IKU 1.2', 'nama' => 'Indikator IKU 1.2', 'unit_id' => $this->unitA->id],
                ['id' => $b1->id, 'kode' => 'IKU 2.1', 'nama' => 'Indikator IKU 2.1', 'unit_id' => $this->unitB->id],
            ]));
        $this->get('/penanggung-jawab?indikator_id='.$a2->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.indicator.id', $a2->id));
        // Kombinasi unit dan indikator berbeda unit tidak memperluas hasil.
        $this->get('/penanggung-jawab?unit_id='.$this->unitA->id.'&indikator_id='.$b1->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0)->where('assignments.total', 0));
        // Nilai di luar pilihan (indikator tanpa PJ, unit acak) jatuh ke semua agar Select tidak menampilkan pilihan palsu.
        $this->get('/penanggung-jawab?indikator_id='.$tanpaPj->id.'&unit_id='.Str::uuid())->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 3)->where('assignments.total', 3)->where('filters.indikator_id', null)->where('filters.unit_id', null));
    }

    public function test_cari_nama_pj_kode_indikator_dan_nama_unit_secara_harfiah(): void
    {
        $a1 = $this->indicator('IKU 1.1', $this->unitA);
        $b1 = $this->indicator('IKU 2.1', $this->unitB);
        $this->assign($a1, $this->actor);
        $this->assign($b1, $this->target);
        $this->actingAs($this->actor);

        foreach (['siti' => $b1, 'iku 1.1' => $a1, 'Kelembagaan' => $b1] as $q => $expected) {
            $this->get('/penanggung-jawab?q='.urlencode($q))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.indicator.id', $expected->id)->where('filters.q', $q));
        }
        $this->get('/penanggung-jawab?q=%25')->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0)->where('assignments.total', 0));
    }

    public function test_tanggal_acuan_memilih_pj_efektif_termasuk_yang_izinnya_lengkap(): void
    {
        $indicator = $this->indicator('IKU 1.1', $this->unitA);
        $this->assign($indicator, $this->target, '2026-01-01');
        $this->assign($indicator, $this->actor, '2026-04-01');
        $this->actingAs($this->actor);

        $this->get('/penanggung-jawab?tanggal_acuan=2026-03-15')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.pic.id', $this->target->id));
        $this->get('/penanggung-jawab?tanggal_acuan=2026-04-01')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.pic.id', $this->actor->id)
            ->where('assignments.data.0.readiness.complete', true));
        $this->get('/penanggung-jawab?tanggal_acuan=2025-12-31')->assertOk()->assertInertia(fn (Assert $page) => $page->has('assignments.data', 0)->where('assignments.total', 0));
    }

    public function test_pj_nonaktif_tetap_tampil_dengan_alasan_akun_nonaktif(): void
    {
        $indicator = $this->indicator('IKU 1.1', $this->unitA);
        $row = $this->assign($indicator, $this->target);
        $this->target->update(['status' => 'nonaktif']);

        $this->actingAs($this->actor)->get('/penanggung-jawab?izin=kurang')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.total', 1)->where('assignments.data.0.pic.status', 'nonaktif')
            ->where('assignments.data.0.readiness.complete', false)
            ->where('assignments.data.0.readiness.permissions.0.reason', 'inactive_user'));
        $this->assertDatabaseHas('penanggung_jawab', ['id' => $row->id, 'user_id' => $this->target->id]);
    }

    public function test_paginasi_dua_puluh_baris_dengan_tautan_membawa_filter(): void
    {
        $this->candidates(21, $this->unitA, withRole: false);
        $this->actingAs($this->actor);

        $this->get('/penanggung-jawab?izin=kurang&tanggal_acuan=2026-03-15')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 20)->where('assignments.data.0.indicator.kode', 'MON-00001')
            ->where('assignments.total', 21)->where('assignments.from', 1)->where('assignments.to', 20)
            ->where('assignments.next_page_url', fn ($url) => is_string($url) && str_contains($url, 'page=2')
                && str_contains($url, 'izin=kurang') && str_contains($url, 'tanggal_acuan=2026-03-15')));
        $this->get('/penanggung-jawab?izin=kurang&tanggal_acuan=2026-03-15&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('assignments.data', 1)->where('assignments.data.0.indicator.kode', 'MON-00021')->where('assignments.next_page_url', null));
    }

    public function test_izin_dievaluasi_per_pasangan_pj_unit_dan_hanya_untuk_halaman_tanpa_filter_izin(): void
    {
        $this->candidates(60, $this->unitA, withRole: true);
        $acl = fn (array $filters) => $this->aclQueries(fn () => app(MonitorPenanggungJawab::class)->handle($filters));

        [$result, $count] = $acl(['tanggal_acuan' => '2026-03-15']);
        $this->assertSame(20, $count);
        $this->assertSame(60, $result['assignments']->total());
        [$result, $count] = $acl(['tanggal_acuan' => '2026-03-15', 'izin' => 'lengkap']);
        $this->assertSame(60, $count);
        $this->assertSame(60, $result['assignments']->total());

        // Satu PJ pada banyak indikator di unit yang sama dievaluasi sekali.
        DB::table('penanggung_jawab')->update(['user_id' => $this->actor->id]);
        [$result, $count] = $acl(['tanggal_acuan' => '2026-03-15', 'izin' => 'lengkap']);
        $this->assertSame(1, $count);
        $this->assertSame(60, $result['assignments']->total());
    }

    public function test_diagnosis_grant_deny_dan_izin_tidak_dikenali_tetap_dari_resolver(): void
    {
        $indicator = $this->indicator('IKU 1.1', $this->unitA);
        $this->attachRole($this->target, 'pegawai');
        $this->assign($indicator, $this->target);
        $permission = Permission::where('kode', 'pengukuran:create')->sole();
        $readiness = fn () => app(MonitorPenanggungJawab::class)->handle(['tanggal_acuan' => '2026-03-15'])['assignments']->items()[0]['readiness'];
        DB::table('user_permission_granted')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id,
            'permission_id' => $permission->id, 'unit_id' => $this->unitA->id,
            'alasan' => 'Fixture grant monitoring', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $granted = $readiness();
        $this->assertCount(6, $granted['missing']);
        $this->assertTrue($granted['permissions'][0]['allowed']);
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->target->id,
            'permission_id' => $permission->id, 'unit_id' => $this->unitA->id,
            'alasan' => 'Fixture deny monitoring', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $denied = $readiness();
        $this->assertCount(7, $denied['missing']);
        $this->assertSame('explicit_deny', $denied['permissions'][0]['reason']);
        $permission->update(['aktif' => false]);
        $this->assertSame('unknown_permission', $readiness()['permissions'][0]['reason']);
    }

    public function test_tanpa_izin_kelola_penanggung_jawab_ditolak(): void
    {
        $this->actingAs($this->target)->get('/penanggung-jawab')->assertForbidden();
        $this->actingAs($this->actor)->get('/penanggung-jawab')->assertOk();
    }

    private function attachRole(User $user, string $code): void
    {
        $user->roles()->attach(Role::where('kode', $code)->value('id'), ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
    }

    private function indicator(string $kode, Unit $unit): IndikatorKinerja
    {
        return IndikatorKinerja::create([
            'sasaran_strategis_id' => $this->sasaran->id, 'unit_id' => $unit->id,
            'kode' => $kode, 'nama' => 'Indikator '.$kode, 'satuan' => 'poin',
            'tipe_perhitungan' => 'manual', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->actor->id, 'created_by_role' => 'perencanaan',
        ]);
    }

    private function assign(IndikatorKinerja $indicator, User $user, string $date = '2026-01-01'): PenugasanIndikator
    {
        return PenugasanIndikator::create(['indikator_id' => $indicator->id, 'user_id' => $user->id,
            'tanggal_mulai_berlaku' => $date, 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    /** Fixture massal: satu PJ berbeda per indikator agar setiap baris punya pasangan PJ×unit sendiri. */
    private function candidates(int $count, Unit $unit, bool $withRole): void
    {
        $users = User::factory()->count($count)->create(['status' => 'aktif']);
        $template = $this->indicator('MON-TEMPLATE', $unit);
        $roleId = Role::where('kode', 'perencanaan')->value('id');
        $indicators = $assignments = $roles = [];
        foreach ($users as $index => $user) {
            $id = (string) Str::uuid();
            $indicators[] = array_replace($template->getAttributes(), ['id' => $id, 'kode' => sprintf('MON-%05d', $index + 1)]);
            $assignments[] = ['id' => (string) Str::uuid(), 'indikator_id' => $id, 'user_id' => $user->id,
                'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()];
            $roles[] = ['id' => (string) Str::uuid(), 'user_id' => $user->id, 'role_id' => $roleId,
                'sumber_pemberian' => 'manual', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()];
        }
        DB::table('indikator_kinerjas')->insert($indicators);
        DB::table('penanggung_jawab')->insert($assignments);
        if ($withRole) {
            DB::table('user_roles')->insert($roles);
        }
    }

    /** @return array{0: mixed, 1: int} hasil dan jumlah pembacaan katalog izin oleh resolver (satu per pasangan PJ×unit). */
    private function aclQueries(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $result = $callback();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        return [$result, count(array_filter($queries, fn ($query) => str_contains($query['query'], 'from "permissions"')))];
    }
}
