<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RencanaAksiPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const INDEX_UNIK = 'CREATE UNIQUE INDEX ra_target_unik ON public.rencana_aksi_target USING btree (rencana_aksi_id, periode_id, komponen_id) NULLS NOT DISTINCT';

    private const MIGRASI_INDEX_UNIK = 'migrations/2026_10_09_100000_satukan_index_unik_target_rencana_aksi.php';

    public function test_header_menolak_duplikat_indikator_tahun(): void
    {
        $this->assertTrue(Schema::hasColumn('rencana_aksi', 'alasan_deviasi_pk'));
        $this->assertFalse(Schema::hasColumn('rencana_aksi', 'jadwal_snapshot_id'));

        $actor = $this->aktor();
        $indikator = $this->buatIndikator($actor);
        $jadwal = $this->buatJadwal($indikator->sasaranStrategis->renstra);

        $atribut = [
            'indikator_id' => $indikator->id,
            'tahun' => 2026,
            'unit_id' => $indikator->unit_id,
            'jadwal_tahunan_id' => $jadwal->id,
            'penanggung_jawab_id' => $actor->id,
            'created_by' => $actor->id,
        ];
        RencanaAksi::factory()->create($atribut);

        $this->assertDatabaseCount('rencana_aksi', 1);

        try {
            DB::transaction(fn () => RencanaAksi::factory()->create($atribut));
            $this->fail('Header duplikat indikator × tahun harus ditolak basis data.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        $this->assertDatabaseCount('rencana_aksi', 1);
    }

    public function test_target_menolak_duplikat_periode_komponen(): void
    {
        $actor = $this->aktor();
        $indikator = $this->buatIndikator($actor);
        $jadwal = $this->buatJadwal($indikator->sasaranStrategis->renstra);
        $periode = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $komponen = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'n',
            'label' => 'Pembilang fixture',
            'peran' => 'pembilang',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $actor->id,
        ]);
        $rencanaAksi = RencanaAksi::factory()->create([
            'indikator_id' => $indikator->id,
            'tahun' => 2026,
            'unit_id' => $indikator->unit_id,
            'jadwal_tahunan_id' => $jadwal->id,
            'penanggung_jawab_id' => $actor->id,
            'created_by' => $actor->id,
        ]);

        RencanaAksiTarget::factory()->create([
            'rencana_aksi_id' => $rencanaAksi->id,
            'periode_id' => $periode->id,
            'komponen_id' => null,
            'nilai' => 10,
            'updated_by' => $actor->id,
        ]);
        RencanaAksiTarget::factory()->create([
            'rencana_aksi_id' => $rencanaAksi->id,
            'periode_id' => $periode->id,
            'komponen_id' => $komponen->id,
            'nilai' => 20,
            'updated_by' => $actor->id,
        ]);

        $this->assertDatabaseCount('rencana_aksi_target', 2);

        try {
            DB::transaction(fn () => RencanaAksiTarget::factory()->create([
                'rencana_aksi_id' => $rencanaAksi->id,
                'periode_id' => $periode->id,
                'komponen_id' => null,
                'nilai' => 30,
                'updated_by' => $actor->id,
            ]));
            $this->fail('Baris manual duplikat (komponen NULL) harus ditolak basis data.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        try {
            DB::transaction(fn () => RencanaAksiTarget::factory()->create([
                'rencana_aksi_id' => $rencanaAksi->id,
                'periode_id' => $periode->id,
                'komponen_id' => $komponen->id,
                'nilai' => 40,
                'updated_by' => $actor->id,
            ]));
            $this->fail('Baris komponen duplikat harus ditolak basis data.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        $this->assertDatabaseCount('rencana_aksi_target', 2);
    }

    /**
     * Satu index unik `NULLS NOT DISTINCT` menggantikan dua index unik parsial
     * dan index biasa per `rencana_aksi_id`.
     */
    public function test_index_unik_target_tunggal_nulls_not_distinct(): void
    {
        $this->assertSame(['ra_target_unik' => self::INDEX_UNIK], $this->indexTarget());
    }

    public function test_migrasi_index_unik_target_dapat_dibalik(): void
    {
        $migrasi = require database_path(self::MIGRASI_INDEX_UNIK);

        $migrasi->down();
        $this->assertSame([
            'ra_target_komponen_unik' => 'CREATE UNIQUE INDEX ra_target_komponen_unik ON public.rencana_aksi_target USING btree (rencana_aksi_id, periode_id, komponen_id) WHERE (komponen_id IS NOT NULL)',
            'ra_target_manual_unik' => 'CREATE UNIQUE INDEX ra_target_manual_unik ON public.rencana_aksi_target USING btree (rencana_aksi_id, periode_id) WHERE (komponen_id IS NULL)',
            'ra_target_rencana_aksi_idx' => 'CREATE INDEX ra_target_rencana_aksi_idx ON public.rencana_aksi_target USING btree (rencana_aksi_id)',
        ], $this->indexTarget());

        $migrasi->up();
        $this->assertSame(['ra_target_unik' => self::INDEX_UNIK], $this->indexTarget());
    }

    /**
     * Index pada `rencana_aksi_target` selain primary key, berurutan nama.
     *
     * @return array<string, string>
     */
    private function indexTarget(): array
    {
        return collect(DB::select("SELECT indexname, indexdef FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'rencana_aksi_target' AND indexname <> 'rencana_aksi_target_pkey' ORDER BY indexname"))
            ->mapWithKeys(fn (object $baris): array => [$baris->indexname => $baris->indexdef])
            ->all();
    }

    private function aktor(): User
    {
        $this->seed(AccessCatalogSeeder::class);

        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', 'superadmin')->firstOrFail();
        $user->roles()->attach($role->id, [
            'id' => (string) Str::uuid(),
            'sumber_pemberian' => 'manual',
            'diberikan_oleh' => $user->id,
            'created_at' => now(),
        ]);

        return $user;
    }

    private function buatIndikator(User $actor): IndikatorKinerja
    {
        $unit = Unit::create(['nama' => 'Unit Uji RA', 'status' => 'aktif', 'created_by' => $actor->id]);
        $renstra = Renstra::create([
            'kode' => 'R-UJI-RA',
            'nama' => 'Renstra Uji RA',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'created_by' => $actor->id,
        ]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-RA', 'deskripsi' => 'Sasaran uji RA']);

        return IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-RA',
            'nama' => 'Indikator Uji RA',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'manual',
            'status' => 'aktif',
            'tahun_mulai_berlaku' => $renstra->tahun_mulai,
            'created_by' => $actor->id,
            'created_by_role' => 'superadmin',
        ]);
    }

    private function buatJadwal(Renstra $renstra): JadwalTahunan
    {
        return JadwalTahunan::create([
            'renstra_id' => $renstra->id,
            'tahun' => 2026,
            'penutupan' => '2026-12-31',
            'status' => 'draft',
        ]);
    }
}
