<?php

namespace Tests\Feature;

use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Services\Pengingat\PengingatKontekstual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class PengingatMultiUnitTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    public function test_fokus_mempertahankan_scope_asal_pada_banner_dan_tabel(): void
    {
        $unitLain = $this->unitBaru('Unit Kedua');
        $snapshot = $this->indikatorBaru('I-UNIT-LAIN', ['unit_id' => $unitLain->id], ['unit_id' => $unitLain->id]);
        $this->headerUntuk($snapshot);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'), 'unit_id' => $unitLain->id,
            'alasan' => 'Pengujian scope asal', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $item = app(PengingatKontekstual::class)->handle($this->actor, 'rencana_aksi:read', false)['pribadi'][0];

        $this->assertStringContainsString('1 Dalam proses · Draf', $item['keterangan']);
        $this->actingAs($this->actor)->get($item['href'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)->has('pengukurans', 1)
            ->where('pengingat.pribadi.0.keterangan', $item['keterangan'])
            ->where('pengingat.pribadi.0.href', $item['href']));
        $normal = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->get($normal['href'])->assertOk()->assertInertia(fn ($page) => $page->where('pagination.total', 2));
    }

    public function test_banner_satu_periode_merangkum_semua_unit_pj_dan_sesuai_tabel_fokus(): void
    {
        $unitLain = $this->unitBaru('Unit Kedua');
        $snapshot = $this->indikatorBaru('I-UNIT-LAIN', ['unit_id' => $unitLain->id], ['unit_id' => $unitLain->id]);
        $this->headerUntuk($snapshot);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $items = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'];

        $this->assertCount(1, $items);
        $this->assertStringContainsString('2 Dalam proses · Draf', $items[0]['keterangan']);
        $this->assertNotNull($items[0]['href']);
        $this->actingAs($this->actor)->get($items[0]['href'])->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.fokus', 'kewajiban')->where('pagination.total', 2)
                ->has('pengukurans', 2));
    }

    public function test_kewajiban_pribadi_tetap_global_saat_filter_dashboard_berubah(): void
    {
        $unitLain = $this->unitBaru('Unit Kedua');
        $snapshot = $this->indikatorBaru('I-UNIT-LAIN', ['unit_id' => $unitLain->id], ['unit_id' => $unitLain->id]);
        $this->headerUntuk($snapshot);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $tw2 = $this->tambahPeriode(2, '2026-03-10', '2026-03-15');
        $personal = app(PengingatKontekstual::class)->handle($this->actor, 'dashboard:read')['pribadi'];
        $this->assertCount(2, $personal);
        foreach ([[$this->pengukuran->periode_id, $this->unit->id, '2 Dalam proses · Draf'],
            [$tw2->periode_id, $unitLain->id, '2 Belum mengisi']] as [$periodeId, $unitId, $status]) {
            $this->actingAs($this->actor)->get('/dashboard?'.http_build_query(['periode_id' => $periodeId, 'unit_id' => $unitId]))
                ->assertOk()->assertInertia(fn ($page) => $page->where('pengingat.pribadi', $personal)
                ->where('stats.total', 1)->where('filter.periode_id', $periodeId)->where('filter.unit_id', $unitId)
                ->where('pengingat.ringkasan.0.keterangan', 'Batas pengisian 15 Maret 2026. 1 dari 1 indikator belum diajukan.'));
            $this->assertTrue(collect($personal)->contains(fn ($item) => str_contains($item['keterangan'], $status)));
        }
    }

    public function test_indikator_sebelum_periode_efektif_dan_jendela_belum_dibuka_tidak_dihitung(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-03-16', '2026-03-22');
        $snapshot = $this->indikatorBaru('I-BARU', [], ['periode_mulai_id' => $tw2->periode_id]);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->actor->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $items = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'];

        $this->assertCount(1, $items);
        $this->assertSame('Batas pengisian 15 Maret 2026. 1 Dalam proses · Draf.', $items[0]['keterangan']);
        $this->actingAs($this->actor)->get($items[0]['href'])->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)->where('pengukurans.0.indikator_id', $this->context->indikator_id));
        $this->get('/pengukuran?'.http_build_query(['fokus' => 'kewajiban', 'jadwal_id' => $this->jadwal->id,
            'periode_id' => $tw2->periode_id]))->assertForbidden();
    }

    public function test_jumlah_banner_utuh_sedangkan_tabel_fokus_dipaginate(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $snapshot = $this->indikatorBaru(sprintf('I-%02d', $i));
            $this->headerUntuk($snapshot);
            PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $this->actor->id,
                'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        }
        $item = app(PengingatKontekstual::class)->handle($this->actor)['pribadi'][0];
        $this->assertStringContainsString('21 Dalam proses · Draf', $item['keterangan']);
        foreach ([1 => 20, 2 => 1] as $number => $count) {
            $this->actingAs($this->actor)->get($item['href'].'&page='.$number)->assertOk()->assertInertia(fn ($page) => $page
                ->where('pagination.total', 21)->has('pengukurans', $count)
                ->where('pengingat.pribadi.0.keterangan', $item['keterangan']));
        }
    }
}
