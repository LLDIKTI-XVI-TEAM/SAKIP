<?php

namespace Tests\Feature\Dashboard;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardFilterTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private function tambahTw2(): PeriodeJadwal
    {
        $periode = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);

        return PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id,
            'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15', 'reviu_mulai' => '2026-06-15', 'reviu_selesai' => '2026-07-15']);
    }

    private function tambahUnitDenganSnapshot(string $nama): Unit
    {
        $unit = Unit::create(['nama' => $nama, 'created_by' => $this->actor->id]);
        $indikator = IndikatorKinerja::create([...$this->pengukuran->indikator->only(['sasaran_strategis_id', 'nama', 'satuan', 'tipe_perhitungan', 'created_by_role']),
            'unit_id' => $unit->id, 'kode' => 'I-'.Str::upper(Str::random(4)), 'status' => 'aktif', 'tahun_mulai_berlaku' => 2026, 'created_by' => $this->actor->id]);
        JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'periode_mulai_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'indikator_id' => $indikator->id, 'unit_id' => $unit->id]);

        return $unit;
    }

    private function tolak(string $kode, ?string $unitId): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    public function test_default_memilih_renstra_aktif_tahun_berjalan_dan_periode_aktif(): void
    {
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.renstra_id', $this->jadwal->renstra_id)->where('filter.tahun', 2026)
            ->where('filter.periode_id', $this->pengukuran->periode_id)->where('filter.sasaran_id', null)->where('filter.unit_id', null)
            ->where('pilihan.renstra.0.id', $this->jadwal->renstra_id)->where('pilihan.tahun', [2026])
            ->where('pilihan.periode', [['id' => $this->pengukuran->periode_id, 'nama' => 'Triwulan I']])
            ->where('pilihan.sasaran.0.id', $this->pengukuran->indikator->sasaran_strategis_id)
            ->where('pilihan.unit', [['id' => $this->unit->id, 'nama' => 'Unit Pengujian']]));
    }

    public function test_pilihan_unit_tidak_memuat_unit_yang_ditolak(): void
    {
        $unitB = $this->tambahUnitDenganSnapshot('Unit B');
        $this->tolak('dashboard:read', $unitB->id);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pilihan.unit', [['id' => $this->unit->id, 'nama' => 'Unit Pengujian']]));
    }

    public function test_unit_ditolak_lewat_url_ditolak_validasi_dan_unit_lain_diterima(): void
    {
        $unitB = $this->tambahUnitDenganSnapshot('Unit B');
        $this->tolak('dashboard:read', $unitB->id);

        $this->actingAs($this->actor)->from('/dashboard?unit_id='.$unitB->id)->get('/dashboard?unit_id='.$unitB->id)
            ->assertSessionHasErrors('unit_id')->assertRedirect('/dashboard');
        $this->get('/dashboard?unit_id='.$this->unit->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.unit_id', $this->unit->id));
    }

    public function test_periode_bukan_anggota_jadwal_atau_belum_dibuka_ditolak(): void
    {
        $tw2 = $this->tambahTw2();

        $this->actingAs($this->actor)->get('/dashboard?periode_id='.$tw2->periode_id)->assertSessionHasErrors('periode_id');
        $this->get('/dashboard?periode_id='.Str::uuid())->assertSessionHasErrors('periode_id');
        $this->get('/dashboard?periode_id='.$this->pengukuran->periode_id)->assertOk();
    }

    public function test_jadwal_draft_tidak_pernah_dipilih(): void
    {
        Renstra::whereKey($this->jadwal->renstra_id)->update(['is_aktif' => false, 'status' => Renstra::STATUS_NONAKTIF]);
        $renstra = Renstra::create(['kode' => 'R-DRAF', 'nama' => 'Renstra Draf', 'tahun_mulai' => 2026, 'tahun_selesai' => 2030, 'is_aktif' => true, 'created_by' => $this->actor->id]);
        JadwalTahunan::create([...$this->jadwal->only(['renstra_pk_id', 'penutupan']), 'renstra_id' => $renstra->id, 'tahun' => 2026, 'status' => 'draft']);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeRenstra.id', $renstra->id)->where('activeJadwal', null)->where('activePeriode', null)->where('pilihan.tahun', [])
            // Renstra yang sedang tampil selalu ada di pilihan agar pengguna dapat berpindah dari dan ke Renstra itu.
            ->where('pilihan.renstra.0.id', $renstra->id)->where('pilihan.renstra.1.id', $this->jadwal->renstra_id));
        $this->get('/dashboard?renstra_id='.$renstra->id)->assertOk();
        $this->get('/dashboard?renstra_id='.$this->jadwal->renstra_id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeRenstra.tahun_mulai', 2025)->where('activeRenstra.tahun_selesai', 2029)->where('pilihan.tahun', [2026]));
    }

    public function test_periode_dibuka_tepat_pukul_nol_wita(): void
    {
        $tw2 = $this->tambahTw2();

        $this->travelTo(Carbon::parse('2026-05-31 15:59:59', 'UTC'));
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pilihan.periode', [['id' => $this->pengukuran->periode_id, 'nama' => 'Triwulan I']]));

        $this->travelTo(Carbon::parse('2026-05-31 16:00:00', 'UTC'));
        $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pilihan.periode', 2)->where('filter.periode_id', $tw2->periode_id)->where('activePeriode.status', 'aktif'));
    }

    public function test_jadwal_ditutup_tidak_menawarkan_periode_setelah_penutupan(): void
    {
        $this->tambahTw2();
        $this->jadwal->update(['status' => 'ditutup', 'closed_at' => Carbon::parse('2026-04-01 02:00:00', 'UTC')]);
        $this->travelTo(now()->setDate(2026, 7, 1));

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activeJadwal.status', 'ditutup')
            ->where('pilihan.periode', [['id' => $this->pengukuran->periode_id, 'nama' => 'Triwulan I']])
            ->where('filter.periode_id', $this->pengukuran->periode_id));
    }

    public function test_tahun_dan_sasaran_di_luar_pilihan_ditolak(): void
    {
        $this->actingAs($this->actor)->get('/dashboard?tahun=2025')->assertSessionHasErrors('tahun');
        $this->get('/dashboard?sasaran_id='.Str::uuid())->assertSessionHasErrors('sasaran_id');
        $this->get('/dashboard?tahun=2026&sasaran_id='.$this->pengukuran->indikator->sasaran_strategis_id)->assertOk();
    }

    public function test_jadwal_ditutup_tidak_memiliki_periode_aktif_dan_draft_lewat_jendela_tidak_mengisi(): void
    {
        // Ditutup di tengah jendela TW I (1–15 Maret): tidak ada periode aktif, tetapi jendela tetap dinilai pada tanggal bisnis (PRD §23).
        $this->jadwal->update(['status' => 'ditutup', 'closed_at' => Carbon::parse('2026-03-09 02:00:00', 'UTC')]);
        $this->travelTo(now()->setDate(2026, 3, 10));
        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activePeriode.status', 'terakhir')->where('statusIndikator.0.status', 'dalam_proses'));

        $this->travelTo(now()->setDate(2026, 4, 1));
        $this->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activePeriode.status', 'terakhir')->where('statusIndikator.0.status', 'tidak_mengisi'));
    }

    public function test_pilihan_unit_memuat_unit_kegiatan_dalam_cakupan_jadwal_walau_tanpa_snapshot(): void
    {
        $kegiatan = fn (Unit $unit) => Kegiatan::create(['unit_id' => $unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan '.$unit->nama, 'anggaran' => 0, 'created_by' => $this->actor->id]);
        $unitBebas = $this->unitBaru('Unit Kegiatan Bebas');
        $kegiatan($unitBebas);
        $unitDitolak = $this->unitBaru('Unit Ditolak');
        $kegiatan($unitDitolak);
        $this->tolak('dashboard:read', $unitDitolak->id);
        // Kegiatan yang hanya diklaim Rencana Aksi jadwal lain tidak masuk panel (Q64 §64.2 butir 3); unitnya pun tidak ditawarkan.
        $renstraLama = Renstra::create([...Renstra::findOrFail($this->jadwal->renstra_id)->only(['nama', 'tahun_mulai', 'tahun_selesai', 'created_by']), 'kode' => 'REN-LAMA', 'status' => 'nonaktif']);
        $jadwalLama = JadwalTahunan::create([...$this->jadwal->only(['tahun', 'penutupan']), 'renstra_id' => $renstraLama->id, 'status' => 'ditutup']);
        $unitLama = $this->unitBaru('Unit Jadwal Lama');
        $raLama = RencanaAksi::create([...$this->plan->only(['tahun', 'penanggung_jawab_id', 'created_by']), 'unit_id' => $unitLama->id, 'jadwal_tahunan_id' => $jadwalLama->id,
            'indikator_id' => $this->indikatorBaru('I-LAMA', snapshot: ['jadwal_id' => $jadwalLama->id])->indikator_id, 'status_alur' => 'draft']);
        KlaimKegiatan::create(['rencana_aksi_id' => $raLama->id, 'kegiatan_id' => $kegiatan($unitLama)->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pilihan.unit', [['id' => $unitBebas->id, 'nama' => 'Unit Kegiatan Bebas'], ['id' => $this->unit->id, 'nama' => 'Unit Pengujian']]));
        $this->get('/dashboard?unit_id='.$unitBebas->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.unit_id', $unitBebas->id)->where('kegiatan.per_status.rencana', 1)->where('statusIndikator', []));
    }

    public function test_filter_periode_mengganti_ringkasan_status_dan_panel_kegiatan(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $this->sahkan($this->pengukuran, ['nilai' => '80']);
        Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id, 'nama' => 'Kegiatan TW I', 'anggaran' => 0, 'created_by' => $this->actor->id]);
        $this->travelTo(now()->setDate(2026, 6, 5));
        $ringkas = fn (AssertableInertia $page, string $periode, string $status, int $disahkan, int $rencana) => $page->where('filter.periode_id', $periode)
            ->where('statusIndikator.0.status', $status)->where('stats.disahkan', $disahkan)->where('kegiatan.per_status.rencana', $rencana);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $ringkas($page, $tw2->periode_id, 'belum_mengisi', 0, 0));
        $this->get('/dashboard?periode_id='.$this->pengukuran->periode_id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $ringkas($page, $this->pengukuran->periode_id, 'belum_ditetapkan', 1, 1)
            ->where('statusIndikator.0.realisasi', '80')->where('activePeriode.status', 'terakhir'));
    }

    public function test_filter_tahun_memilih_jadwal_tahun_lain_pada_renstra_yang_sama(): void
    {
        $jadwal2025 = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2025, 'penutupan' => '2025-12-31', 'status' => 'ditutup',
            'closed_at' => Carbon::parse('2025-12-31 01:00:00', 'UTC')]);
        PeriodeJadwal::create(['jadwal_id' => $jadwal2025->id, 'periode_id' => $this->pengukuran->periode_id,
            'pengisian_mulai' => '2025-03-01', 'pengisian_selesai' => '2025-03-15', 'reviu_mulai' => '2025-03-15', 'reviu_selesai' => '2025-04-15']);
        JadwalSnapshot::create([...$this->context->only(['indikator_id', 'periode_mulai_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'jadwal_id' => $jadwal2025->id]);

        $this->actingAs($this->actor)->get('/dashboard')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.tahun', 2026)->where('pilihan.tahun', [2026, 2025])->where('statusIndikator.0.status', 'dalam_proses'));
        $this->get('/dashboard?tahun=2025')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.tahun', 2025)->where('activeJadwal', ['tahun' => 2025, 'status' => 'ditutup'])->where('statusIndikator.0.status', 'belum_mengisi'));
    }

    public function test_renstra_di_luar_pilihan_dan_pengenal_salah_bentuk_ditolak(): void
    {
        $this->actingAs($this->actor)->get('/dashboard?renstra_id='.Str::uuid())->assertSessionHasErrors('renstra_id');
        // Bentuk pengenal divalidasi FormRequest sekaligus, sebelum dicocokkan dengan pilihan.
        $this->get('/dashboard?renstra_id=1&periode_id=xyz&unit_id=abc')->assertSessionHasErrors(['renstra_id', 'periode_id', 'unit_id']);
    }

    public function test_pengenal_huruf_besar_diterima_dan_dinormalkan(): void
    {
        $this->actingAs($this->actor)->get('/dashboard?unit_id='.strtoupper($this->unit->id))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter.unit_id', $this->unit->id));
    }
}
