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
use App\Models\Renstra;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi Review8 V1 (F2 guard simpan-404 + F3 rollback non-destruktif).
 *
 * F2: `SimpanTargetPeriodeRequest::authorize()` abort 404 bila header tak
 * ditemukan SEBELUM validasi `exists` — cermin `PreviewTargetPeriodeRequest`
 * (T1/Review6). Tanpa ini UUID asing + payload tak valid memberi 422
 * sedangkan payload valid memberi 404 (oracle 422-vs-404), dan jalur
 * tanpa-izin memberi oracle 403-vs-404.
 *
 * F3: `down()` migrasi backfill U2 hanya me-NULL-kan pin yang benar-benar
 * diisi `up()` (ditandai tabel sisi deterministik). Jepit sah pra-existing
 * yang kebetulan sama nilainya dengan peta backup tak tersentuh karena tak
 * pernah masuk penanda. Tanpa penanda (up lama pra-V1), down() no-op
 * non-destruktif.
 */
class RencanaAksiReview8V1Test extends TestCase
{
    use RefreshDatabase;

    private const BACKUP_TABLE = '_backup_rencana_aksi_jadwal_snapshot_20261004';

    private const MARKER_TABLE = '_backfill_snapshot_draf_rencana_aksi_u2_ids';

    private const MIGRATION_PATH = 'migrations/2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2.php';

    public function test_simpan_uuid_asing_dengan_izin_tetap_404_tanpa_422(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Sanity: header sah tetap tersimpan agar fix tak mematahkan jalur sah.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $asing = (string) Str::uuid();

        // Payload berbentuk valid atas UUID asing → 404 (bukan 403/422).
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$asing}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertNotFound();

        // Payload tak valid (`exists` gagal) atas UUID asing → tetap 404,
        // bukan 422 yang membocorkan keberadaan.
        $this->actingAs($fixture['pic'])->postJson("/rencana-aksi/{$asing}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => (string) Str::uuid(),
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => (string) Str::uuid(), 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ])->assertNotFound();
    }

    public function test_simpan_uuid_asing_tanpa_izin_tak_pernah_422(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();

        $tanpaIzin = $this->penggunaDenganPeran('admin');
        $asing = (string) Str::uuid();

        $responsValid = $this->actingAs($tanpaIzin)->postJson("/rencana-aksi/{$asing}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ]);
        $this->assertContains($responsValid->getStatusCode(), [403, 404]);

        $responsTakValid = $this->actingAs($tanpaIzin)->postJson("/rencana-aksi/{$asing}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => (string) Str::uuid(),
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => (string) Str::uuid(), 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
            ],
        ]);
        $this->assertContains($responsTakValid->getStatusCode(), [403, 404]);
    }

    public function test_down_hanya_kembalikan_pin_yang_benar_benar_diisi_up(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Draf lama pra-backfill: pin NULL + peta backup ke v1.
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => null]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $header->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ]);

        // Jepit sah pra-existing yang tak terbedakan nilainya: pin sudah
        // terisi v1 SEBELUM up() + peta backup bernilai sama. up() wajib
        // melewatinya (syarat IS NULL) dan down() wajib mempertahankannya.
        $praExisting = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2025,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'snapshot_draf_id' => $fixture['snapshot']->id,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'created_by' => $fixture['pic']->id,
        ]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $praExisting->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ]);

        $this->migrasiBackfill()->up();

        $this->assertSame($fixture['snapshot']->id, $header->fresh()->snapshot_draf_id);
        $this->assertSame($fixture['snapshot']->id, $praExisting->fresh()->snapshot_draf_id);
        $this->assertTrue(Schema::hasTable(self::MARKER_TABLE));
        $this->assertTrue(DB::table(self::MARKER_TABLE)->where('rencana_aksi_id', $header->id)->exists());
        $this->assertFalse(DB::table(self::MARKER_TABLE)->where('rencana_aksi_id', $praExisting->id)->exists());

        $this->migrasiBackfill()->down();

        // Pin backfill dikembalikan, pin pra-existing utuh.
        $this->assertNull($header->fresh()->snapshot_draf_id);
        $this->assertSame($fixture['snapshot']->id, $praExisting->fresh()->snapshot_draf_id);
        $this->assertFalse(Schema::hasTable(self::MARKER_TABLE));
    }

    public function test_down_tanpa_penanda_bersifat_non_destruktif(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        // Simulasi hasil up() lama pra-V1 (tanpa penanda): pin memegang nilai
        // backfill persis tetapi tabel sisi hilang.
        DB::table('rencana_aksi')->where('id', $header->id)->update(['snapshot_draf_id' => $fixture['snapshot']->id]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $header->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ]);
        DB::statement(sprintf('DROP TABLE IF EXISTS "%s"', self::MARKER_TABLE));

        $this->migrasiBackfill()->down();

        // Non-destruktif: jepit yang tak dapat dibedakan dipertahankan.
        $this->assertSame($fixture['snapshot']->id, $header->fresh()->snapshot_draf_id);
    }

    private function migrasiBackfill(): object
    {
        static $migrasi = null;

        if ($migrasi === null) {
            $migrasi = require database_path(self::MIGRATION_PATH);
        }

        return $migrasi;
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R8V1', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R8V1', 'nama' => 'Renstra Uji R8V1', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R8V1', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji R8V1',
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
