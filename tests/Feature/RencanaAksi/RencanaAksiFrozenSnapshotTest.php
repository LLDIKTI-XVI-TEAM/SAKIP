<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RencanaAksiFrozenSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_jadwal_aktif_tanpa_snapshot_menolak_create_dan_save(): void
    {
        $fixture = $this->buatFixtureTanpaSnapshot();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('snapshot');
        $this->assertDatabaseCount('rencana_aksi', 0);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.buat_ditolak')->exists());

        $this->actingAs($fixture['perencanaan'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasErrors('snapshot');
        $this->assertDatabaseCount('rencana_aksi', 0);

        $header = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => 'draft',
            'versi' => 1,
            'created_by' => $fixture['perencanaan']->id,
        ]);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => null,
            'expected_snapshot_versi' => null,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('snapshot');
        $this->assertSame(1, $header->fresh()->versi);
        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah_ditolak')->where('objek_id', $header->id)->exists());

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")->assertSessionHasErrors('snapshot');
    }

    public function test_master_berubah_setelah_aktivasi_ra_tetap_memakai_snapshot_lama(): void
    {
        $fixture = $this->buatFixtureRasio();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $fixture['indikator']->update(['tipe_perhitungan' => 'manual', 'presisi' => 5]);
        $fixture['pembilang']->update(['kode' => 'n_baru', 'label' => 'Pembilang Diubah']);
        IndikatorKomponen::create([
            'indikator_id' => $fixture['indikator']->id,
            'kode' => 'x',
            'label' => 'Komponen Baru Master',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 3,
            'aktif' => true,
            'created_by' => $fixture['perencanaan']->id,
        ]);

        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 70, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets');

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.tipe_perhitungan', 'rasio_persen')
                ->where('rencanaAksi.presisi', 2)
                ->where('rencanaAksi.target_pk', '100.000000000000'));
    }

    public function test_jadwal_ditutup_dengan_koreksi_sah_tetap_memakai_frozen_snapshot_terbaru(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK.',
            'rujukan_koreksi' => 'SK-KOREKSI-001',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 150,
        ]);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 80, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $fixture['indikator']->update(['tipe_perhitungan' => 'rasio_persen', 'presisi' => 5]);

        $fixture['jadwal']->update([
            'status' => 'ditutup',
            'penutupan' => '2026-03-05',
            'closed_at' => now(),
            'koreksi_mulai' => '2026-03-01 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$fixture['indikator']->id],
            ],
        ]);

        $header->refresh();
        $this->actingAs($fixture['perencanaan'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 65, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 85, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['perencanaan'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.tipe_perhitungan', 'manual')
                ->where('rencanaAksi.presisi', 2)
                ->where('rencanaAksi.target_pk', '150.000000000000'));
    }

    public function test_konteks_beku_tidak_mengikuti_master(): void
    {
        $fixture = $this->buatFixtureRasioMulaiKedua();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 40, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $fixture['indikator']->update(['tipe_perhitungan' => 'manual', 'presisi' => 5]);
        $fixture['pembilang']->update(['kode' => 'n_baru', 'label' => 'Pembilang Diubah Master']);
        $fixture['penyebut']->update(['label' => 'Penyebut Diubah Master']);
        $fixture['snapshot']->update(['target' => 100]);

        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets');

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.tipe_perhitungan', 'rasio_persen')
                ->where('rencanaAksi.presisi', 2)
                ->where('rencanaAksi.target_pk', '100.000000000000')
                ->where('rencanaAksi.periode.0.id', $fixture['periode1']->id)
                ->where('rencanaAksi.periode.0.efektif', false)
                ->where('rencanaAksi.periode.1.id', $fixture['periode2']->id)
                ->where('rencanaAksi.periode.1.efektif', true)
                ->where('rencanaAksi.komponen.0.kode', 'n')
                ->where('rencanaAksi.komponen.0.label', 'Pembilang')
                ->where('rencanaAksi.komponen.1.kode', 't')
                ->where('rencanaAksi.komponen.1.label', 'Penyebut'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Beku', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-BEKU', 'nama' => 'Renstra Uji Beku', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-BEKU', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Beku Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periode1->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'manual',
            'target' => 100,
        ]);
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureTanpaSnapshot(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Tanpa Beku', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-NOBEKU', 'nama' => 'Renstra Tanpa Beku', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-NOBEKU', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Tanpa Beku',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureRasio(): array
    {
        $dasar = $this->buatFixtureManualRasio('manual_ke_rasio');

        return $dasar;
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManualRasio(string $suffix): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Rasio '.$suffix, 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-'.Str::upper(Str::random(4)), 'nama' => 'Renstra Uji Rasio', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-'.Str::upper(Str::random(4)), 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Rasio Beku',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'rasio_persen',
            'arah' => 'naik_baik',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'status' => 'aktif',
            'tahun_mulai_berlaku' => 2025,
            'created_by' => $perencanaan->id,
            'created_by_role' => 'perencanaan',
        ]);

        $periode1 = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2] as $periode) {
            PeriodeJadwal::create([
                'jadwal_id' => $jadwal->id,
                'periode_id' => $periode->id,
                'pengisian_mulai' => '2026-03-01',
                'pengisian_selesai' => '2026-03-31',
                'reviu_mulai' => '2026-04-01',
                'reviu_selesai' => '2026-04-30',
            ]);
        }
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'nomor_versi' => 1,
            'periode_mulai_id' => $periode1->id,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'rasio_persen',
            'target' => 100,
        ]);
        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 't',
            'label' => 'Penyebut',
            'peran' => 'penyebut',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        foreach ([$pembilang, $penyebut] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $snapshot->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }
        PenugasanIndikator::create([
            'indikator_id' => $indikator->id,
            'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01',
            'ditetapkan_oleh' => $perencanaan->id,
            'created_at' => now(),
        ]);

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot', 'pembilang', 'penyebut');
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureRasioMulaiKedua(): array
    {
        $fixture = $this->buatFixtureManualRasio('mulai_kedua');
        $fixture['snapshot']->update(['periode_mulai_id' => $fixture['periode2']->id]);

        return $fixture;
    }

    private function penggunaDenganPeran(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function grant(User $user, string $permission, string $unitId, User $oleh): void
    {
        DB::table('user_permission_granted')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Fixture pengujian',
            'diberikan_oleh' => $oleh->id,
            'created_at' => now(),
        ]);
    }
}
