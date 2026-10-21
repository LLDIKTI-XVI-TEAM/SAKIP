<?php

namespace Tests\Feature\Dashboard;

use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class DashboardKegiatanTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** @return array<string, mixed> */
    private function panel(?User $user = null, string $query = ''): array
    {
        return $this->actingAs($user ?? $this->actor)->get('/dashboard'.$query)->assertOk()->viewData('page')['props']['kegiatan'];
    }

    private function kegiatan(array $attributes = []): Kegiatan
    {
        return Kegiatan::create(['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'nama' => 'Kegiatan '.Str::random(5), 'anggaran' => 0, 'created_by' => $this->actor->id, ...$attributes]);
    }

    private function klaim(RencanaAksi $rencanaAksi, Kegiatan $kegiatan): void
    {
        KlaimKegiatan::create(['rencana_aksi_id' => $rencanaAksi->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah',
            'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->actor->id, 'created_at' => now()]);
    }

    private function rencanaAksiBaru(string $kode, array $indikator = []): RencanaAksi
    {
        $snapshot = $this->indikatorBaru($kode, $indikator);

        return RencanaAksi::create([...$this->plan->only(['tahun', 'unit_id', 'jadwal_tahunan_id', 'penanggung_jawab_id', 'created_by']),
            'indikator_id' => $snapshot->indikator_id, 'status_alur' => 'draft']);
    }

    public function test_hitungan_per_status_termasuk_batal_dan_tidak_terlaksana(): void
    {
        foreach (Kegiatan::STATUSES as $status) {
            $this->kegiatan(['status' => $status, 'justifikasi' => 'Alasan pengujian', 'tanggal_realisasi' => $status === 'terlaksana' ? '2026-03-10' : null]);
        }

        $this->assertSame(['rencana' => 1, 'terlaksana' => 1, 'tidak_terlaksana' => 1, 'ditunda' => 1, 'batal' => 1], $this->panel()['per_status']);
    }

    public function test_kegiatan_lanjutan_hasil_geser_periode_dihitung_terpisah_dari_asal(): void
    {
        $tw2 = $this->tambahPeriode(2, '2026-06-01', '2026-06-15');
        $asal = $this->kegiatan(['status' => 'ditunda', 'justifikasi' => 'Ditunda pengujian']);
        $this->kegiatan(['kegiatan_asal_id' => $asal->id, 'periode_id' => $tw2->periode_id]);
        $this->travelTo(now()->setDate(2026, 6, 5));
        $hitung = fn (array $panel) => [$panel['per_status']['ditunda'], $panel['per_status']['rencana']];

        // Plan 7.7: lanjutan dihitung di periode tujuan, asal tetap di periode asal; tidak dobel-hitung.
        $this->assertSame([0, 1], $hitung($this->panel()));
        $this->assertSame([1, 0], $hitung($this->panel(query: '?periode_id='.$this->pengukuran->periode_id)));
    }

    public function test_kegiatan_lewat_tanggal_rencana_belum_terlaksana_diberi_peringatan(): void
    {
        $terlambat = $this->kegiatan(['nama' => 'Lewat tanggal', 'tanggal_rencana' => '2026-03-14']);
        $this->kegiatan(['tanggal_rencana' => '2026-03-16']);
        $this->kegiatan(['status' => 'terlaksana', 'tanggal_rencana' => '2026-03-14', 'tanggal_realisasi' => '2026-03-14']);

        $panel = $this->panel();

        $this->assertSame(1, $panel['terlambat_jumlah']);
        $this->assertSame([['id' => $terlambat->id, 'nama' => 'Lewat tanggal', 'unit' => 'Unit Pengujian', 'tanggal_rencana' => '2026-03-14']], $panel['terlambat']);
    }

    public function test_perubahan_status_langsung_tercermin(): void
    {
        $kegiatan = $this->kegiatan();
        $this->assertSame(1, $this->panel()['per_status']['rencana']);

        $kegiatan->update(['status' => 'terlaksana', 'tanggal_realisasi' => '2026-03-14']);

        $this->assertSame([0, 1], [$this->panel()['per_status']['rencana'], $this->panel()['per_status']['terlaksana']]);
    }

    public function test_kegiatan_bersama_muncul_di_setiap_iku(): void
    {
        $bersama = $this->kegiatan();
        $this->klaim($this->plan, $bersama);
        $this->klaim($this->rencanaAksiBaru('I-DUA'), $bersama);

        $panel = $this->panel();

        $this->assertSame(1, $panel['per_status']['rencana']);
        $this->assertSame(['I-DUA', 'I-UJI'], array_column($panel['per_iku'], 'kode'));
        $this->assertSame([1, 1], array_map(fn ($iku) => $iku['jumlah']['rencana'], $panel['per_iku']));
        $this->assertSame($this->pengukuran->indikator_id, $panel['per_iku'][1]['id']);
    }

    public function test_filter_sasaran_lewat_klaim(): void
    {
        $sasaran = SasaranStrategis::create(['renstra_id' => $this->jadwal->renstra_id, 'kode' => 'S-DUA', 'deskripsi' => 'Sasaran Dua']);
        $this->klaim($this->plan, $this->kegiatan());
        $this->klaim($this->rencanaAksiBaru('I-DUA', ['sasaran_strategis_id' => $sasaran->id]), $this->kegiatan(['status' => 'batal', 'justifikasi' => 'Batal pengujian']));

        $panel = $this->panel(query: '?sasaran_id='.$sasaran->id);

        $this->assertSame([0, 1], [$panel['per_status']['rencana'], $panel['per_status']['batal']]);
        $this->assertSame(['I-DUA'], array_column($panel['per_iku'], 'kode'));
    }

    public function test_nama_terlambat_mengikuti_unit_berizin_tetapi_jumlah_tetap(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->kegiatan(['nama' => 'Terlambat A', 'tanggal_rencana' => '2026-03-01']);
        $this->kegiatan(['nama' => 'Terlambat B', 'unit_id' => $unitB->id, 'tanggal_rencana' => '2026-03-02']);
        $pegawai = $this->userWithRole('pegawai');
        $this->grant($pegawai, 'pengukuran:create');

        $panel = $this->panel($pegawai);
        $this->assertSame([2, ['Terlambat A']], [$panel['terlambat_jumlah'], array_column($panel['terlambat'], 'nama')]);

        $this->assertSame(['Terlambat A', 'Terlambat B'], array_column($this->panel($this->userWithRole('pimpinan'))['terlambat'], 'nama'));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pegawai->id, 'permission_id' => Permission::where('kode', 'kegiatan:read')->value('id'),
            'unit_id' => null, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $panel = $this->panel($pegawai);
        $this->assertSame([2, null], [$panel['terlambat_jumlah'], $panel['terlambat']]);
    }

    public function test_unit_ditolak_dashboard_dikeluarkan_dari_panel_kegiatan(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->klaim($this->plan, $this->kegiatan(['nama' => 'Terlambat A', 'tanggal_rencana' => '2026-03-01']));
        $kegiatanB = $this->kegiatan(['nama' => 'Terlambat B', 'unit_id' => $unitB->id, 'tanggal_rencana' => '2026-03-02']);
        $this->klaim($this->rencanaAksiBaru('I-DUA'), $kegiatanB);
        $ringkas = fn (array $panel) => [$panel['per_status']['rencana'], $panel['terlambat_jumlah'], array_column($panel['per_iku'], 'kode'), array_column($panel['terlambat'], 'nama')];

        $this->assertSame([2, 2, ['I-DUA', 'I-UJI'], ['Terlambat A', 'Terlambat B']], $ringkas($this->panel()));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'permission_id' => Permission::where('kode', 'dashboard:read')->value('id'),
            'unit_id' => $unitB->id, 'alasan' => 'Deny pengujian', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->assertSame([1, 1, ['I-UJI'], ['Terlambat A']], $ringkas($this->panel()));
    }

    public function test_kegiatan_dibatasi_jadwal_terpilih_dan_kegiatan_belum_diklaim_tetap_dihitung(): void
    {
        $renstraLama = Renstra::create([...Renstra::findOrFail($this->jadwal->renstra_id)->only(['nama', 'tahun_mulai', 'tahun_selesai', 'created_by']), 'kode' => 'REN-LAMA', 'status' => 'nonaktif']);
        $jadwalLama = JadwalTahunan::create([...$this->jadwal->only(['tahun', 'penutupan']), 'renstra_id' => $renstraLama->id, 'status' => 'ditutup']);
        PeriodeJadwal::create([...PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail()->only(['periode_id', 'pengisian_mulai', 'pengisian_selesai', 'reviu_mulai', 'reviu_selesai']),
            'jadwal_id' => $jadwalLama->id]);
        $indikatorLama = $this->indikatorBaru('I-LAMA', snapshot: ['jadwal_id' => $jadwalLama->id]);
        $raLama = RencanaAksi::create([...$this->plan->only(['tahun', 'unit_id', 'penanggung_jawab_id', 'created_by']),
            'jadwal_tahunan_id' => $jadwalLama->id, 'indikator_id' => $indikatorLama->indikator_id, 'status_alur' => 'draft']);
        $this->klaim($raLama, $this->kegiatan(['nama' => 'K-lama', 'tanggal_rencana' => '2026-03-01']));
        $this->klaim($this->plan, $this->kegiatan(['nama' => 'K-baru', 'tanggal_rencana' => '2026-03-02']));
        $this->kegiatan(['nama' => 'K-bebas', 'tanggal_rencana' => '2026-03-03']);
        $ringkas = fn (array $panel) => [$panel['per_status']['rencana'], $panel['terlambat_jumlah'], array_column($panel['terlambat'], 'nama'), array_column($panel['per_iku'], 'kode')];

        $this->assertSame([2, 2, ['K-baru', 'K-bebas'], ['I-UJI']], $ringkas($this->panel()));
        $this->assertSame([2, 2, ['K-lama', 'K-bebas'], ['I-LAMA']], $ringkas($this->panel(query: '?renstra_id='.$renstraLama->id)));
    }

    public function test_filter_sasaran_hanya_mengikuti_klaim_rencana_aksi_jadwal_terpilih(): void
    {
        $renstraLama = Renstra::create([...Renstra::findOrFail($this->jadwal->renstra_id)->only(['nama', 'tahun_mulai', 'tahun_selesai', 'created_by']), 'kode' => 'REN-LAMA', 'status' => 'nonaktif']);
        $jadwalLama = JadwalTahunan::create([...$this->jadwal->only(['tahun', 'penutupan']), 'renstra_id' => $renstraLama->id, 'status' => 'ditutup']);
        // Sasaran S-DUA ada di jadwal terpilih (I-S2, tanpa klaim) dan di jadwal lama (I-LAMA, mengklaim K-bersama).
        $sasaranDua = SasaranStrategis::create(['renstra_id' => $this->jadwal->renstra_id, 'kode' => 'S-DUA', 'deskripsi' => 'Sasaran Dua']);
        $this->indikatorBaru('I-S2', indikator: ['sasaran_strategis_id' => $sasaranDua->id]);
        $indikatorLama = $this->indikatorBaru('I-LAMA', indikator: ['sasaran_strategis_id' => $sasaranDua->id], snapshot: ['jadwal_id' => $jadwalLama->id]);
        $raLama = RencanaAksi::create([...$this->plan->only(['tahun', 'unit_id', 'penanggung_jawab_id', 'created_by']),
            'jadwal_tahunan_id' => $jadwalLama->id, 'indikator_id' => $indikatorLama->indikator_id, 'status_alur' => 'draft']);
        $bersama = $this->kegiatan(['nama' => 'K-bersama', 'tanggal_rencana' => '2026-03-01']);
        $this->klaim($this->plan, $bersama);
        $this->klaim($raLama, $bersama);

        $panel = $this->panel(query: '?sasaran_id='.$sasaranDua->id);

        $this->assertSame([0, 0, [], []], [$panel['per_status']['rencana'], $panel['terlambat_jumlah'], $panel['terlambat'], $panel['per_iku']]);
        $this->assertSame(1, $this->panel(query: '?sasaran_id='.$this->pengukuran->indikator->sasaran_strategis_id)['per_status']['rencana']);
    }

    public function test_filter_unit_menyaring_panel_kegiatan(): void
    {
        $unitB = $this->unitBaru('Unit B');
        $this->kegiatan(['nama' => 'K-A', 'tanggal_rencana' => '2026-03-01']);
        $this->kegiatan(['nama' => 'K-B', 'unit_id' => $unitB->id, 'tanggal_rencana' => '2026-03-02']);
        $this->indikatorBaru('I-B', indikator: ['unit_id' => $unitB->id], snapshot: ['unit_id' => $unitB->id]);

        $panel = $this->panel(query: '?unit_id='.$unitB->id);

        $this->assertSame([1, 1, ['K-B']], [$panel['per_status']['rencana'], $panel['terlambat_jumlah'], array_column($panel['terlambat'], 'nama')]);
    }
}
