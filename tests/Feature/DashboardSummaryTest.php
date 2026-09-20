<?php

namespace Tests\Feature;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_index_excludes_another_schedule_in_the_same_year_and_period(): void
    {
        $oldSchedule = JadwalTahunan::create([...$this->jadwal->only(['renstra_id', 'tahun', 'renstra_pk_id', 'penutupan']), 'status' => 'ditutup']);
        $oldIndicator = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'unit_id', 'nama', 'satuan', 'tipe_perhitungan']), 'kode' => 'I-LAMA']);
        $oldContext = JadwalSnapshot::create([...$this->context->only(['periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan']),
            'jadwal_id' => $oldSchedule->id, 'indikator_id' => $oldIndicator->id]);
        PengukuranKinerja::create([...$this->pengukuran->only(['tahun', 'periode_id', 'sumber_nilai', 'created_by']),
            'indikator_id' => $oldIndicator->id, 'jadwal_snapshot_id' => $oldContext->id]);

        $this->actingAs($this->actor)->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)->where('pengukurans.0.id', $this->pengukuran->id)->where('pagination.total', 1));
    }

    public function test_measurement_index_is_empty_before_the_first_schedule_window(): void
    {
        $this->travelTo(now()->setDate(2026, 2, 15));

        $this->actingAs($this->actor)->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page
            ->where('periode', null)->has('pengukurans', 0)->where('pagination.total', 0));

        $this->travelTo(Carbon::parse('2026-02-28 16:00:00', 'UTC'));
        $this->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page
            ->where('periode.id', $this->pengukuran->periode_id)->where('pagination.total', 1));
    }

    public function test_dashboard_uses_latest_started_period_and_is_empty_before_any_window_starts(): void
    {
        Renstra::whereKey($this->jadwal->renstra_id)->update(['is_aktif' => true]);
        $next = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $next->id,
            'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15', 'reviu_mulai' => '2026-06-15', 'reviu_selesai' => '2026-07-15']);
        $this->travelTo(now()->setDate(2026, 4, 1));
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activePeriode.id', $this->pengukuran->periode_id)->where('stats.total', 1)->has('pengukurans', 1));

        $this->travelTo(now()->setDate(2026, 2, 15));
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activePeriode', null)->where('stats.total', 0)->has('pengukurans', 0));

        $this->travelTo(Carbon::parse('2026-02-28 16:00:00', 'UTC'));
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activePeriode.id', $this->pengukuran->periode_id)->where('stats.total', 1));
    }

    public function test_dashboard_does_not_fall_back_to_old_data_without_an_active_renstra_schedule(): void
    {
        Renstra::query()->update(['is_aktif' => false]);
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activeRenstra', null)->where('activePeriode', null)->where('stats.total', 0)->has('pengukurans', 0));

        $renstra = Renstra::create(['kode' => 'R-BARU', 'nama' => 'Renstra Baru', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_aktif' => true]);
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activeRenstra.id', $renstra->id)->where('activePeriode', null)->where('stats.total', 0)->has('pengukurans', 0));
    }

    public function test_dashboard_selects_the_active_renstra_covering_the_current_year(): void
    {
        Renstra::whereKey($this->jadwal->renstra_id)->update(['is_aktif' => true]);
        Renstra::create(['kode' => 'R-LAMA', 'nama' => 'Renstra Lama', 'tahun_mulai' => 2020, 'tahun_selesai' => 2024, 'is_aktif' => true]);
        Renstra::create(['kode' => 'R-DEPAN', 'nama' => 'Renstra Mendatang', 'tahun_mulai' => 2030, 'tahun_selesai' => 2034, 'is_aktif' => true]);
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activeRenstra.id', $this->jadwal->renstra_id)->where('stats.total', 1));

        Renstra::whereKey($this->jadwal->renstra_id)->update(['is_aktif' => false]);
        $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activeRenstra', null)->where('activePeriode', null)->where('stats.total', 0));
    }

    public function test_dashboard_does_not_replace_current_schedule_with_next_year(): void
    {
        Renstra::whereKey($this->jadwal->renstra_id)->update(['is_aktif' => true]);
        JadwalTahunan::create([...$this->jadwal->only(['renstra_id', 'renstra_pk_id']),
            'tahun' => 2027, 'penutupan' => '2027-12-31', 'status' => 'aktif']);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
            ->where('activePeriode.nama_periode', 'Triwulan I 2026')->where('stats.total', 1)->where('pengukurans.0.id', $this->pengukuran->id));
        $this->get('/pengukuran')->assertOk()->assertInertia(fn ($page) => $page
            ->where('periode.tahun', 2026)->where('pagination.total', 1)->where('pengukurans.0.id', $this->pengukuran->id));
    }

    public function test_summary_batches_current_pic_for_draft_and_returned_rows_but_keeps_frozen_pic(): void
    {
        $futurePic = $this->userWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $futurePic->id,
            'tanggal_mulai_berlaku' => '2026-04-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $returned = $this->pengukuran->replicate();
        $returned->periode_id = Periode::create(['nama' => 'Tahunan', 'urutan' => 5, 'aktif' => true, 'is_nilai_akhir' => true])->id;
        $returned->status_alur = 'dikembalikan';
        $returned->save();
        $present = app(PresentPengukuran::class);
        $rows = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot.jadwal', 'jadwalSnapshot.unit', 'latestVersion', 'ratifiedVersion'])->get();
        DB::enableQueryLog();
        $present->prepareSummary($rows);
        foreach ($rows as $row) {
            $this->assertSame($this->actor->only(['id', 'nama']), $present->handle($row, $this->actor)['penugasan_indikator']['pic']);
        }
        $picQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], '"penanggung_jawab"'));
        DB::disableQueryLog();
        $this->assertCount(1, $picQueries);

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 85])->assertSessionHasNoErrors();
        $this->travelTo(now()->setDate(2026, 4, 2));
        $rows = PengukuranKinerja::whereKey($this->pengukuran->id)->get();
        $present->prepareSummary($rows);
        $this->assertSame($this->actor->only(['id', 'nama']), $present->handle($rows->first(), $this->actor)['penugasan_indikator']['pic']);
    }
}
