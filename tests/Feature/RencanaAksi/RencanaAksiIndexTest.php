<?php

namespace Tests\Feature\RencanaAksi;

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

class RencanaAksiIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_peringatan_turun_tidak_memblokir_penyimpanan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.0.id', $fixture['periode1']->id)
                ->where('rencanaAksi.periode.0.efektif', true)
                ->where('rencanaAksi.periode.0.skor.nilai', '20.00')
                ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'terhitung')
                ->where('rencanaAksi.periode.0.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.id', $fixture['periode2']->id)
                ->where('rencanaAksi.periode.1.skor.nilai', '10.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', true)
                ->where('rencanaAksi.periode.1.komponen_turun', [null]));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.komponen_turun', []));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => null, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', null)
                ->where('rencanaAksi.periode.1.skor.status_perhitungan', 'belum_diisi')
                ->where('rencanaAksi.periode.1.peringatan_turun', false));
    }

    public function test_deviasi_pk_butuh_alasan_dan_tersimpan_sebagai_simpan(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 80, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.target_pk', '100.000000000000')
                ->where('rencanaAksi.deviasi_pk.dapat_dinilai', true)
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', true)
                ->where('rencanaAksi.deviasi_pk.alasan_terisi', false)
                ->where('rencanaAksi.deviasi_pk.skor_periode_terakhir', '80.00')
                ->where('rencanaAksi.deviasi_pk.periode_id', $fixture['periode2']->id));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'alasan_deviasi_pk' => 'Realisasi lapangan di bawah target PK tahunan.',
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 80, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame('draft', $header->status_alur);
        $this->assertSame('Realisasi lapangan di bawah target PK tahunan.', $header->alasan_deviasi_pk);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_terisi', true)
                ->where('rencanaAksi.alasan_deviasi_pk', 'Realisasi lapangan di bawah target PK tahunan.'));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 60, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.deviasi_pk.ada', false)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', false));
    }

    public function test_nonmanual_menampilkan_skor_turunan_peringatan_dan_deviasi(): void
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
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 80, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.tipe_perhitungan', 'rasio_persen')
                ->has('rencanaAksi.komponen', 2)
                ->where('rencanaAksi.periode.0.skor.nilai', '50.00')
                ->where('rencanaAksi.periode.0.skor.status_perhitungan', 'terhitung')
                ->where('rencanaAksi.periode.0.peringatan_turun', false)
                ->where('rencanaAksi.periode.1.skor.nilai', '80.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', false)
                ->where('rencanaAksi.deviasi_pk.ada', true)
                ->where('rencanaAksi.deviasi_pk.alasan_diperlukan', true));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 30, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', '30.00')
                ->where('rencanaAksi.periode.1.peringatan_turun', true)
                ->where('rencanaAksi.periode.1.komponen_turun', [$fixture['pembilang']->id]));

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->fresh()->id}/target", [
            'expected_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 100, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['pembilang']->id, 'nilai' => 5, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['penyebut']->id, 'nilai' => 0, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.1.skor.nilai', null)
                ->where('rencanaAksi.periode.1.skor.status_perhitungan', 'tidak_dapat_dihitung')
                ->where('rencanaAksi.deviasi_pk.dapat_dinilai', false)
                ->where('rencanaAksi.deviasi_pk.ada', false));
    }

    public function test_baca_menolak_tanpa_izin_dan_menandai_can(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $tanpaIzin = $this->penggunaDenganPeran('admin');
        $this->actingAs($tanpaIzin)->get("/rencana-aksi/{$header->id}")->assertForbidden();

        $bacaSaja = $this->penggunaDenganPeran('pimpinan');
        $this->actingAs($bacaSaja)->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.can.view', true)
                ->where('rencanaAksi.can.update', false));

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.can.view', true)
                ->where('rencanaAksi.can.update', true)
                ->where('rencanaAksi.expected_versi', 1)
                ->where('rencanaAksi.status_alur', 'draft'));
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(string $tipe = 'manual', int $mulaiUrutan = 1): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji RA Baca', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-RAB', 'nama' => 'Renstra Uji RA Baca', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-RAB', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Baca Uji',
            'satuan' => 'poin',
            'tipe_perhitungan' => $tipe,
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
        $mulaiId = $mulaiUrutan === 2 ? $periode2->id : $periode1->id;
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $jadwal->id,
            'indikator_id' => $indikator->id,
            'periode_mulai_id' => $mulaiId,
            'unit_id' => $unit->id,
            'nama' => $indikator->nama,
            'definisi' => 'Definisi beku.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => $tipe,
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
    private function buatFixtureRasio(): array
    {
        $dasar = $this->buatFixtureManual('rasio_persen', 1);

        $pembilang = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 'n',
            'label' => 'Pembilang',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        $penyebut = IndikatorKomponen::create([
            'indikator_id' => $dasar['indikator']->id,
            'kode' => 't',
            'label' => 'Penyebut',
            'peran' => 'penyebut',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $dasar['perencanaan']->id,
        ]);
        foreach ([$pembilang, $penyebut] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $dasar['snapshot']->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }

        return [...$dasar, 'pembilang' => $pembilang, 'penyebut' => $penyebut];
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
