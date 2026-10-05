<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
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

/**
 * Regresi P3 F2: lingkup koreksi di UI.
 *
 * Backend mengekspos `lingkup_koreksi.periode_ids` via `koreksi` pada
 * payload baca; FE menonaktifkan + tidak mengirim periode luar lingkup
 * (validasi fail-closed N1 tetap di backend). Koreksi parsial 1 dari 4
 * periode harus tersimpan via bentuk UI (hanya periode tercakup dikirim).
 */
class RencanaAksiKoreksiLingkupTest extends TestCase
{
    use RefreshDatabase;

    public function test_payload_mengekspos_lingkup_koreksi_dan_koreksi_parsial_satu_dari_empat_tersimpan(): void
    {
        $fixture = $this->buatFixtureEmpatPeriode();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->tutupJadwalDenganKoreksiSatuPeriode($fixture);

        // F2: payload baca mengekspos lingkup koreksi.
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.koreksi.aktif', true)
                ->where('rencanaAksi.koreksi.periode_ids', [$fixture['periode1']->id]));

        // F2: bentuk UI koreksi parsial — hanya periode tercakup dikirim.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $header->fresh()->versi);
        $this->assertSame(1, RencanaAksiTarget::where('rencana_aksi_id', $header->id)->count());
        $this->assertSame('10.000000000000', (string) RencanaAksiTarget::where('rencana_aksi_id', $header->id)->sole()->nilai);
    }

    public function test_koreksi_menolak_periode_luar_lingkup_fail_closed(): void
    {
        $fixture = $this->buatFixtureEmpatPeriode();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->tutupJadwalDenganKoreksiSatuPeriode($fixture);

        // Periode luar lingkup tetap ditolak backend (N1 fail-closed).
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('jendela');

        $this->assertSame(1, $header->fresh()->versi);
        $this->assertDatabaseCount('rencana_aksi_target', 0);
    }

    public function test_tanpa_koreksi_payload_tidak_membatasi(): void
    {
        $fixture = $this->buatFixtureEmpatPeriode();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.koreksi.aktif', false));
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function tutupJadwalDenganKoreksiSatuPeriode(array $fixture): void
    {
        $fixture['jadwal']->update([
            'status' => 'ditutup',
            'penutupan' => '2026-03-05',
            'koreksi_mulai' => '2026-03-01 00:00:00',
            'koreksi_sampai' => '2026-03-31 23:59:59',
            'lingkup_koreksi' => [
                'jenis_objek' => ['rencana_aksi'],
                'indikator_ids' => [$fixture['indikator']->id],
                'periode_ids' => [$fixture['periode1']->id],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureEmpatPeriode(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji Koreksi F2', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-F2', 'nama' => 'Renstra Uji F2', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-F2', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Koreksi Uji',
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
        $periode2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode3 = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => false]);
        $periode4 = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2, $periode3, $periode4] as $periode) {
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

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'periode3', 'periode4', 'jadwal', 'snapshot');
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
