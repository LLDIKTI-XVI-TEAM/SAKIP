<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\IndikatorKomponenFixtureSeeder;
use Database\Seeders\PeriodeSeeder;
use Database\Seeders\RencanaAksiFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RencanaAksiFixtureSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Header fixture harus berdiri di atas jadwal aktif dengan snapshot beku,
     * karena header tanpa snapshot ditolak fail-closed. Snapshot pada jadwal
     * aktif selalu berkomposisi final, sama seperti hasil aktivasi. Urutan
     * seed mengikuti pemakaian QA, dan seeder RA dijalankan dua kali.
     */
    public function test_header_fixture_dapat_dibuka_dan_disunting_pic(): void
    {
        $this->seed([AccessCatalogSeeder::class, PeriodeSeeder::class, RencanaAksiFixtureSeeder::class, RencanaAksiFixtureSeeder::class, IndikatorKomponenFixtureSeeder::class]);
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $header = RencanaAksi::sole();
        $snapshot = JadwalSnapshot::where('indikator_id', $header->indikator_id)->sole();
        $pic = User::where('email', 'perencanaan@sakip.local')->sole();

        $this->assertSame((string) $snapshot->id, (string) $header->snapshot_draf_id);
        $this->assertTrue($snapshot->komposisi_final);
        $this->actingAs($pic)->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', (string) $snapshot->id)
                ->where('rencanaAksi.can.update', true));
    }

    /**
     * DB yang di-seed versi lama (jadwal draft, atau snapshot non-final) tidak
     * di-upgrade diam-diam: seeder gagal dengan pesan seed ulang, dan karena
     * atomik tidak meninggalkan baris apa pun (termasuk pengguna fixture yang
     * dibuat sebelum pemeriksaan).
     */
    #[DataProvider('fixtureLama')]
    public function test_fixture_versi_lama_ditolak_tanpa_sisa(bool $jadwalAktif): void
    {
        $this->seed([AccessCatalogSeeder::class, PeriodeSeeder::class]);
        $pembuat = User::factory()->create(['status' => 'aktif']);
        $unit = Unit::create(['nama' => 'Unit Fixture Rencana Aksi', 'status' => 'aktif', 'created_by' => $pembuat->id]);
        $renstra = Renstra::create(['kode' => 'RENSTRA-2025-2029', 'nama' => 'Renstra lama', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $pembuat->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'SS-RA-FIXTURE', 'deskripsi' => 'Sasaran lama', 'urutan' => 99]);
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $unit->id, 'kode' => 'IKU-RA-FIXTURE', 'nama' => 'Indikator lama', 'satuan' => 'poin',
            'tipe_perhitungan' => 'manual', 'arah' => 'naik_baik', 'presisi' => 2, 'desimal_tampilan' => 2, 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $pembuat->id, 'created_by_role' => 'perencanaan']);
        $jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => $jadwalAktif ? 'aktif' : 'draft', 'activated_at' => $jadwalAktif ? now() : null]);
        if ($jadwalAktif) {
            JadwalSnapshot::create(['jadwal_id' => $jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => Periode::orderBy('urutan')->value('id'), 'unit_id' => $unit->id,
                'nama' => 'Indikator lama', 'definisi' => 'Definisi lama.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 100]);
        }

        try {
            $this->seed(RencanaAksiFixtureSeeder::class);
            $this->fail('Seeder seharusnya menolak fixture versi lama.');
        } catch (LogicException $galat) {
            $this->assertStringContainsString('seed ulang', $galat->getMessage());
        }

        $this->assertFalse(User::where('email', 'perencanaan@sakip.local')->exists());
        $this->assertSame('Indikator lama', $indikator->fresh()->nama);
        $this->assertDatabaseCount('rencana_aksi', 0);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function fixtureLama(): array
    {
        return ['jadwal draft' => [false], 'snapshot non-final' => [true]];
    }
}
