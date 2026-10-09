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
 * Regresi Review6 T3 (F4 periode-mulai snapshot didahulukan).
 *
 * Bila snapshot ada, `periode_mulai_id` snapshot adalah satu-satunya
 * sumber efektivitas — tahun master live diabaikan. Koreksi master ke
 * atas pasca-aktivasi tak boleh membuat periode ≥ snapshot-mulai tak
 * efektif. Berlaku seragam di tulis (`SimpanTargetPeriode`), baca
 * (`IndexRencanaAksi`), pratinjau (`PreviewTargetPeriode`), dan
 * rekonsiliasi (`RekonsiliasiTargetDraf`, tanpa gerbang tahun karena
 * selalu bersnapshot).
 */
class RencanaAksiReview6T3Test extends TestCase
{
    use RefreshDatabase;

    public function test_tulis_snapshot_didahulukan_atas_master_yang_dikoreksi_ke_atas(): void
    {
        $fixture = $this->buatFixtureTigaPeriode();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Koreksi master ke atas pasca-aktivasi (2025 → 2027); header 2026.
        $fixture['indikator']->update(['tahun_mulai_berlaku' => 2027]);

        // TW II + TW III (≥ snapshot-mulai) tetap tersimpan tanpa 422.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode3']->id, 'komponen_id' => null, 'nilai' => 30, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('20.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode2']->id)->sole()->getRawOriginal('nilai'));
        $this->assertSame('30.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)->where('periode_id', $fixture['periode3']->id)->sole()->getRawOriginal('nilai'));

        // TW I (pra-mulai snapshot) tetap ditolak — bukan blanket-allow.
        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertSessionHasErrors('targets');
    }

    public function test_baca_snapshot_didahulukan_atas_master_yang_dikoreksi_ke_atas(): void
    {
        $fixture = $this->buatFixtureTigaPeriode();
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
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
                ['periode_id' => $fixture['periode3']->id, 'komponen_id' => null, 'nilai' => 30, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        // Koreksi master ke atas pasca-simpan; baca + rekonsiliasi tak
        // boleh menyaring nilai ≥ TW II sebagai basi.
        $fixture['indikator']->update(['tahun_mulai_berlaku' => 2027]);

        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.periode.0.id', $fixture['periode1']->id)
                ->where('rencanaAksi.periode.0.efektif', false)
                ->where('rencanaAksi.periode.1.id', $fixture['periode2']->id)
                ->where('rencanaAksi.periode.1.efektif', true)
                ->where('rencanaAksi.periode.1.nilai.0.nilai', '20.000000000000')
                ->where('rencanaAksi.periode.2.id', $fixture['periode3']->id)
                ->where('rencanaAksi.periode.2.efektif', true)
                ->where('rencanaAksi.periode.2.nilai.0.nilai', '30.000000000000'));
    }

    public function test_preview_snapshot_didahulukan_atas_master_yang_dikoreksi_ke_atas(): void
    {
        $fixture = $this->buatFixtureTigaPeriode();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $fixture['indikator']->update(['tahun_mulai_berlaku' => 2027]);

        // Pratinjau TW II tetap terhitung (bukan 422 periode tak berlaku).
        $respons = $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 25, 'keterangan' => null],
                ['periode_id' => $fixture['periode3']->id, 'komponen_id' => null, 'nilai' => 35, 'keterangan' => null],
            ],
        ])->assertOk();

        $periode = collect($respons->json('periode'))->keyBy('id');
        $this->assertFalse($periode[$fixture['periode1']->id]['efektif']);
        $this->assertTrue($periode[$fixture['periode2']->id]['efektif']);
        $this->assertSame('25.00', $periode[$fixture['periode2']->id]['skor']['nilai']);
        $this->assertTrue($periode[$fixture['periode3']->id]['efektif']);

        // Pratinjau TW I tetap ditolak sebagai tak berlaku.
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$header->id}/preview", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertUnprocessable();
    }

    /**
     * Fixture manual tiga triwulan; snapshot-mulai = TW II agar regresi
     * membuktikan TW I tak berlaku sedangkan TW II + TW III efektif
     * walau master dikoreksi ke atas.
     *
     * @return array<string, mixed>
     */
    private function buatFixtureTigaPeriode(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R6T3', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R6T3', 'nama' => 'Renstra Uji R6T3', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R6T3', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji R6T3',
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
        $periode3 = Periode::create(['nama' => 'Triwulan III', 'urutan' => 3, 'aktif' => true, 'is_nilai_akhir' => true]);
        $jadwal = JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'rencana_aksi_mulai' => '2026-03-01',
            'rencana_aksi_selesai' => '2026-03-31',
            'penutupan' => '2026-12-31',
            'status' => 'aktif',
            'activated_at' => now(),
        ]);
        foreach ([$periode1, $periode2, $periode3] as $periode) {
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
            'periode_mulai_id' => $periode2->id,
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

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'periode3', 'jadwal', 'snapshot');
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
