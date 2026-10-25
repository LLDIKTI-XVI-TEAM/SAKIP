<?php

namespace Tests\Feature\Periode;

use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IndikatorKomponenFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedProduksiTanpaFixtureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabel yang hanya diisi fixture testing, bukan oleh DatabaseSeeder.
     *
     * @var list<string>
     */
    private const TABEL_FIXTURE = [
        'users',
        'user_roles',
        'unit',
        'regulasi',
        'jenis_berkas',
        'berkas',
        'renstras',
        'renstra_pk',
        'target_renstra',
        'sasaran_strategis',
        'indikator_kinerjas',
        'indikator_komponen',
        'target_kinerjas',
        'jadwal_tahunan',
        'jadwal_periode',
        'jadwal_snapshot',
        'jadwal_snapshot_komponen',
        'rencana_aksi',
        'rencana_aksi_versi',
        'rencana_aksi_target',
        'kegiatan',
        'klaim_kegiatan',
        'pengukuran_kinerjas',
        'pengukuran_komponen',
        'pengukuran_versi',
        'riwayat_pengukurans',
        'rekomendasi_pimpinan',
        'penanggung_jawab',
    ];

    /**
     * AC-3 ISS-15.01: seed produksi hanya memuat data konfigurasi (katalog
     * akses, pengaturan, periode) dan tidak membawa fixture testing.
     */
    public function test_database_seeder_tidak_membuat_data_fixture(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('periode', 4);

        foreach (self::TABEL_FIXTURE as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());
        $this->assertGreaterThan(0, DB::table('pengaturan')->count());
    }

    /**
     * AC-3 ISS-15.01: pemisahan data uji dan konfigurasi produksi tetap
     * menjaga fixture dapat dipanggil eksplisit dari test.
     */
    public function test_fixture_tetap_dapat_dipanggil_eksplisit_dari_test(): void
    {
        // Fixture menurunkan peran creator dari katalog akses, sehingga katalog
        // harus disemai lebih dulu (sama seperti pemakaian QA/manual).
        $this->seed(AccessCatalogSeeder::class);
        $this->seed(IndikatorKomponenFixtureSeeder::class);

        $this->assertGreaterThan(0, DB::table('indikator_kinerjas')->count());
        $this->assertGreaterThan(0, DB::table('indikator_komponen')->count());
    }
}
