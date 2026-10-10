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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi pembekuan penambahan komponen pasca-terbit.
 *
 * Keputusan: (a) finalisasi atomik snapshot+komponen sebelum publik lalu tolak
 * seluruh INSERT komponen pasca-publik, BUKAN (b) ubah identitas versi tiap
 * komposisi berubah. Alasan: selaras immutable-sejak-terbit — identitas
 * versi stabil sebagai token konkurensi (`expected_snapshot_id` +
 * `expected_snapshot_versi`); opsi (b) memaksa bump semu tiap sisipan sehingga
 * token basi + rekonsiliasi transisi berisik tanpa koreksi resmi. Publikasi =
 * INSERT snapshot (false) + INSERT komponen + UPDATE finalisasi true dalam satu
 * transaksi; pasca-finalisasi INSERT ditolak walau belum dijepit (celah: v2
 * tampil + pin lama), koreksi sah tetap via sisipan berversi (snapshot baru +
 * komponennya selagi induk baru belum final).
 */
class RencanaAksiFinalisasiKomposisiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION_PATH = 'migrations/2026_10_06_032010_bekukan_komposisi_snapshot_terbit_rencana_aksi_v2.php';

    public function test_insert_komponen_ke_snapshot_final_yang_ditampilkan_ditolak(): void
    {
        $fixture = $this->buatFixturePenjumlahan();
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
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        // Finalisasi v1 (publikasi awal) agar komposisi v1 ikut beku.
        $fixture['snapshot']->update(['komposisi_final' => true]);
        $this->assertTrue($fixture['snapshot']->fresh()->komposisi_final);

        // Terbitkan v2 lengkap (A+B) secara atomik: snapshot false + komponen
        // (populasi awal lolos karena tak dirujuk + belum final) + finalisasi.
        $v2 = $this->terbitkanSnapshotLengkap($fixture, 2, $fixture['snapshot']->id, false);
        $v2->update(['komposisi_final' => true]);
        $this->assertTrue($v2->fresh()->komposisi_final);

        // v2 tampil sebagai terbaru sedangkan jepit masih v1 (tepat celahnya:
        // tampil + pin lama, belum dirujuk pin/pengukuran/versi).
        $this->assertSame($fixture['snapshot']->id, $header->fresh()->snapshot_draf_id);
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', $v2->id)
                ->where('rencanaAksi.expected_snapshot_versi', 2));

        // Sisipan langsung komponen baru ke v2 final wajib ditolak walau belum
        // dirujuk pin. Savepoint bersarang memulihkan transaksi uji pasca-abort.
        $komponenC = IndikatorKomponen::create([
            'indikator_id' => $fixture['indikator']->id,
            'kode' => 'c',
            'label' => 'Komponen C',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 3,
            'aktif' => true,
            'created_by' => $fixture['perencanaan']->id,
        ]);

        try {
            DB::transaction(function () use ($v2, $komponenC): void {
                JadwalSnapshotKomponen::create([
                    'jadwal_snapshot_id' => $v2->id,
                    'komponen_id' => $komponenC->id,
                    'kode' => 'c',
                    'label' => 'Komponen C',
                    'peran' => 'penjumlah',
                    'bobot' => 1,
                    'urutan' => 3,
                ]);
            });
            $this->fail('INSERT komponen ke snapshot final yang ditampilkan harus ditolak.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $v2->id)->count());
    }

    public function test_koreksi_berversi_setelah_finalisasi_tetap_terbuka(): void
    {
        $fixture = $this->buatFixturePenjumlahan();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();

        $fixture['snapshot']->update(['komposisi_final' => true]);

        $v2 = $this->terbitkanSnapshotLengkap($fixture, 2, $fixture['snapshot']->id, false);
        $v2->update(['komposisi_final' => true]);

        // Koreksi sah: snapshot baru v3 (belum final) + komponennya tetap lolos.
        $v3 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 3,
            'menggantikan_id' => $v2->id,
            'alasan_koreksi' => 'Koreksi resmi V2 tambah keterangan.',
            'rujukan_koreksi' => 'SK-KOREKSI-R8V2-001',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v3.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);
        foreach ([$fixture['komponenA'], $fixture['komponenB']] as $index => $komponen) {
            JadwalSnapshotKomponen::create([
                'jadwal_snapshot_id' => $v3->id,
                'komponen_id' => $komponen->id,
                'kode' => $komponen->kode,
                'label' => $komponen->label,
                'peran' => $komponen->peran,
                'bobot' => 1,
                'urutan' => $index + 1,
            ]);
        }
        $this->assertSame(2, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $v3->id)->count());

        $v3->update(['komposisi_final' => true]);
        $this->assertTrue($v3->fresh()->komposisi_final);

        // Simpan di bawah v3 maju — jepit mengikuti, nilai utuh.
        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $v3->id,
            'expected_snapshot_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 55, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => 110, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame($v3->id, $header->fresh()->snapshot_draf_id);
    }

    public function test_finalisasi_hanya_mengizinkan_penguncian_dan_down_aman(): void
    {
        $fixture = $this->buatFixturePenjumlahan();

        // Mutasi data snapshot tetap ditolak walau belum final (guard immutable-sejak-terbit utuh).
        try {
            DB::transaction(function () use ($fixture): void {
                $fixture['snapshot']->update(['target' => 777]);
            });
            $this->fail('Mutasi data snapshot harus ditolak walau belum final.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $fixture['snapshot']->refresh();

        // Finalisasi false->true lolos; no-op true->true lolos; un-finalisasi ditolak.
        $fixture['snapshot']->update(['komposisi_final' => true]);
        $this->assertTrue($fixture['snapshot']->fresh()->komposisi_final);

        DB::table('jadwal_snapshot')->where('id', $fixture['snapshot']->id)->update(['komposisi_final' => true]);
        $this->assertTrue($fixture['snapshot']->fresh()->komposisi_final);

        try {
            DB::transaction(function () use ($fixture): void {
                DB::table('jadwal_snapshot')->where('id', $fixture['snapshot']->id)->update(['komposisi_final' => false]);
            });
            $this->fail('Un-finalisasi snapshot harus ditolak.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        // down() aman non-destruktif: flag dilepas, trigger kembali ke varian immutable-sejak-terbit,
        // baris snapshot/komponen utuh.
        $snapshotId = $fixture['snapshot']->id;
        $komponenCount = JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshotId)->count();

        $this->migrasiBekukanKomposisi()->down();

        $this->assertFalse(Schema::hasColumn('jadwal_snapshot', 'komposisi_final'));
        $this->assertTrue(JadwalSnapshot::whereKey($snapshotId)->exists());
        $this->assertSame($komponenCount, JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshotId)->count());

        // Mutasi data tetap ditolak di bawah guard immutable-sejak-terbit (bukti trigger pulih).
        try {
            DB::transaction(function () use ($snapshotId): void {
                DB::table('jadwal_snapshot')->where('id', $snapshotId)->update(['target' => 888]);
            });
            $this->fail('Mutasi snapshot harus tetap ditolak setelah down().');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $this->migrasiBekukanKomposisi()->up();

        $this->assertTrue(Schema::hasColumn('jadwal_snapshot', 'komposisi_final'));
        $this->assertTrue((bool) DB::table('jadwal_snapshot')->where('id', $snapshotId)->value('komposisi_final'));
    }

    private function migrasiBekukanKomposisi(): object
    {
        static $migrasi = null;

        if ($migrasi === null) {
            $migrasi = require database_path(self::MIGRATION_PATH);
        }

        return $migrasi;
    }

    /**
     * @param  array<string, mixed>  $fixture
     */
    private function terbitkanSnapshotLengkap(array $fixture, int $nomorVersi, string $menggantikanId, bool $finalkanLangsung): JadwalSnapshot
    {
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => $nomorVersi,
            'menggantikan_id' => $menggantikanId,
            'alasan_koreksi' => 'Koreksi resmi R8V2.',
            'rujukan_koreksi' => 'SK-KOREKSI-R8V2-'.str_pad((string) $nomorVersi, 3, '0', STR_PAD_LEFT),
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku R8V2.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
            'komposisi_final' => $finalkanLangsung,
        ]);
        foreach ([$fixture['komponenA'], $fixture['komponenB']] as $index => $komponen) {
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

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function buatFixturePenjumlahan(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R8V2 Jumlah', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R8V2J', 'nama' => 'Renstra Uji R8V2 Jumlah', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R8V2J', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Jumlah Uji R8V2',
            'satuan' => 'poin',
            'tipe_perhitungan' => 'penjumlahan',
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
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);
        $komponenA = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'a',
            'label' => 'Komponen A',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 1,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        $komponenB = IndikatorKomponen::create([
            'indikator_id' => $indikator->id,
            'kode' => 'b',
            'label' => 'Komponen B',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 2,
            'aktif' => true,
            'created_by' => $perencanaan->id,
        ]);
        foreach ([$komponenA, $komponenB] as $index => $komponen) {
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

        return compact('perencanaan', 'pic', 'unit', 'renstra', 'sasaran', 'indikator', 'periode1', 'periode2', 'jadwal', 'snapshot', 'komponenA', 'komponenB');
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
