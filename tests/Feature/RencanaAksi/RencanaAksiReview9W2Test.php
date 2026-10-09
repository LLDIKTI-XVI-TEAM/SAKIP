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
 * Regresi Review9 W2 (F2 penanda backfill atomik).
 *
 * Temuan: up() backfill U2 memilih ID penanda via `SELECT` di statement
 * terpisah SEBELUM `UPDATE`, sehingga write aplikasi yang commit di antara
 * keduanya ikut tertanda tanpa dibackfill. Kasus destruktifnya adalah pin
 * sah yang kebetulan sama nilainya dengan peta backup: sudah masuk penanda
 * (saat masih NULL) lalu diisi aplikasi dengan nilai yang sama persis,
 * sehingga guard nilai down() (`snapshot_draf_id = backup...`) tak
 * melindunginya dan rollback me-NULL-kan pin sah.
 *
 * Perbaikan: satu statement atomik — CTE data-modifying
 * `WITH updated AS (UPDATE ... RETURNING) INSERT INTO penanda SELECT FROM
 * updated`. Satu snapshot PostgreSQL untuk baca+tulis: hanya baris yang
 * benar-benar ter-UPDATE yang tercatat.
 *
 * Batas bukti: orkestrasi dua-koneksi benar-benar paralel (write commit
 * tepat di antara dua statement lama) tak feasible dalam satu proses uji;
 * yang dibuktikan adalah (1) berkas migrasi hanya berisi satu statement
 * data CTE atomik (pola seperti guard-FOR-UPDATE di Review9W1Test), dan
 * (2) perilaku ujungnya: pin sah yang terisi sebelum up() — baik bernilai
 * beda maupun kebetulan sama dengan peta — tak pernah masuk penanda dan
 * selamat dari down(), sementara baris yang benar-benar dibackfill tetap
 * ditandai dan rollback-nya jujur.
 */
class RencanaAksiReview9W2Test extends TestCase
{
    use RefreshDatabase;

    private const BACKUP_TABLE = '_backup_rencana_aksi_jadwal_snapshot_20261004';

    private const MARKER_TABLE = '_backfill_snapshot_draf_rencana_aksi_u2_ids';

    private const MIGRATION_PATH = 'migrations/2026_10_05_160925_backfill_snapshot_draf_rencana_aksi_u2.php';

    public function test_up_menggabungkan_update_dan_penanda_dalam_satu_statement_atomik(): void
    {
        $isi = (string) file_get_contents(database_path(self::MIGRATION_PATH));

        $this->assertMatchesRegularExpression(
            '/WITH\s+updated\s+AS\s*\(\s*UPDATE\s+rencana_aksi/si',
            $isi,
            'up() harus memakai CTE data-modifying WITH updated AS (UPDATE rencana_aksi ...).'
        );
        $this->assertStringContainsString(
            'RETURNING ra.id',
            $isi,
            'UPDATE harus mengembalikan ID via RETURNING untuk pencatatan atomik.'
        );
        $this->assertMatchesRegularExpression(
            '/INSERT INTO.*SELECT\s+id\s+FROM\s+updated/si',
            $isi,
            'Penanda harus diisi dari baris yang benar-benar ter-UPDATE (SELECT FROM updated).'
        );
        $this->assertStringNotContainsString(
            'SELECT ra.id',
            $isi,
            'Tak boleh ada lagi SELECT penanda terpisah dari tabel rencana_aksi (jendela race F2).'
        );
        $up = (string) strstr((string) strstr($isi, 'public function up()'), 'public function down()', true);
        $this->assertSame(
            1,
            substr_count($up, 'UPDATE rencana_aksi AS ra'),
            'up(): hanya boleh ada satu UPDATE rencana_aksi (di dalam CTE atomik).'
        );
    }

    public function test_write_aplikasi_di_antara_tak_ikut_tertanda_dan_rollback_aman(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));
        $v2 = $this->terbitkanSnapshotV2($fixture);

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $backfill = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->where('tahun', 2026)->sole();

        // Kontrol positif: draf lama pra-backfill (NULL + peta ke v1), tanpa
        // write aplikasi — wajib dibackfill sekaligus ditandai.
        DB::table('rencana_aksi')->where('id', $backfill->id)->update(['snapshot_draf_id' => null]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $backfill->id,
            'jadwal_snapshot_id' => $fixture['snapshot']->id,
        ]);

        // Simulasi write aplikasi yang commit "di antara" (sebelum up()
        // berjalan): pin sah bernilai BEDA dari peta backup.
        $korbanBeda = $this->buatBarisNullDenganPeta($fixture, 2025, $fixture['snapshot']->id);
        DB::table('rencana_aksi')->where('id', $korbanBeda->id)->update(['snapshot_draf_id' => $v2->id]);

        // Simulasi kasus destruktif F2: pin sah yang kebetulan SAMA persis
        // dengan peta backup. Dengan dua statement lama, baris ini sudah
        // masuk penanda saat masih NULL sehingga down() me-NULL-kannya.
        $korbanSama = $this->buatBarisNullDenganPeta($fixture, 2024, $fixture['snapshot']->id);
        DB::table('rencana_aksi')->where('id', $korbanSama->id)->update(['snapshot_draf_id' => $fixture['snapshot']->id]);

        $this->migrasiBackfill()->up();

        // Kontrol positif terisi dan tertanda.
        $this->assertSame($fixture['snapshot']->id, $backfill->fresh()->snapshot_draf_id);
        $this->assertTrue(DB::table(self::MARKER_TABLE)->where('rencana_aksi_id', $backfill->id)->exists());

        // Kedua pin sah tak tersentuh dan TAK masuk penanda (atomik: pada
        // saat snapshot tunggal CTE, keduanya sudah non-NULL).
        $this->assertSame($v2->id, $korbanBeda->fresh()->snapshot_draf_id);
        $this->assertFalse(DB::table(self::MARKER_TABLE)->where('rencana_aksi_id', $korbanBeda->id)->exists());
        $this->assertSame($fixture['snapshot']->id, $korbanSama->fresh()->snapshot_draf_id);
        $this->assertFalse(DB::table(self::MARKER_TABLE)->where('rencana_aksi_id', $korbanSama->id)->exists());

        // Aplikasi memajukan hasil backfill ke koreksi v2 sebelum rollback.
        DB::table('rencana_aksi')->where('id', $backfill->id)->update(['snapshot_draf_id' => $v2->id]);

        $this->migrasiBackfill()->down();

        // Rollback non-destruktif: semua pin sah utuh, penanda dibersihkan.
        $this->assertSame($v2->id, $backfill->fresh()->snapshot_draf_id);
        $this->assertSame($v2->id, $korbanBeda->fresh()->snapshot_draf_id);
        $this->assertSame($fixture['snapshot']->id, $korbanSama->fresh()->snapshot_draf_id);
        $this->assertFalse(Schema::hasTable(self::MARKER_TABLE));
    }

    private function buatBarisNullDenganPeta(array $fixture, int $tahun, string $petaSnapshotId): RencanaAksi
    {
        $baris = RencanaAksi::create([
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => $tahun,
            'unit_id' => $fixture['unit']->id,
            'jadwal_tahunan_id' => $fixture['jadwal']->id,
            'snapshot_draf_id' => null,
            'penanggung_jawab_id' => $fixture['pic']->id,
            'status_alur' => RencanaAksi::STATUS_DRAFT,
            'versi' => 1,
            'created_by' => $fixture['pic']->id,
        ]);
        DB::table(self::BACKUP_TABLE)->insert([
            'rencana_aksi_id' => $baris->id,
            'jadwal_snapshot_id' => $petaSnapshotId,
        ]);

        return $baris;
    }

    private function migrasiBackfill(): object
    {
        static $migrasi = null;

        if ($migrasi === null) {
            $migrasi = require database_path(self::MIGRATION_PATH);
        }

        return $migrasi;
    }

    private function terbitkanSnapshotV2(array $fixture): JadwalSnapshot
    {
        return JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK R9W2.',
            'rujukan_koreksi' => 'SK-KOREKSI-R9W2-001',
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
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R9W2', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R9W2', 'nama' => 'Renstra Uji R9W2', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R9W2', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Uji R9W2',
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
