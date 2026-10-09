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
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresi rekonsiliasi transisi snapshot dan pembekuan snapshot yang dijepit draf.
 *
 * Keputusan rekonsiliasi: rekonsiliasi saat snapshot berubah (bukan hanya pasca-POST),
 * bukan ikat target pada snapshot asal. Alasan: kolom
 * snapshot-asal per baris sudah ditolak (menduplikasi kontrak versi beku, menumpuk baris
 * basi, tiap pembaca yang lupa filter = kebocoran lintas-konteks) — lubang
 * yang tersisa hanyalah jendela tanpa-simpan (v1 → v2 tanpa save → v3),
 * yang ditutup dengan jepit header + telusur versi antara: baris basi
 * disaring di baca (tanpa efek samping) dan dibuang teraudit di tulis.
 *
 * Keputusan jepit: simpan rujukan snapshot di draf (kolom non-FK audit-safe
 * `snapshot_draf_id`, tanpa mengembalikan FK otorisasi) agar
 * trigger menolak mutasi langsung + token 409 mendeteksi versi baru.
 * Trigger immutable global ditolak: memblokir koreksi-sisipan yang sah
 * (komponen pelengkap versi baru) dan koreksi pra-draf, serta menyimpang
 * dari filosofi beku-berbasis-rujukan repo.
 */
class RencanaAksiRekonsiliasiTransisiSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_transisi_lewat_tanpa_simpan_tak_membangkitkan_nilai_basi(): void
    {
        $fixture = $this->buatFixturePenjumlahan();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['snapshot']->id, $header->snapshot_draf_id);
        $this->assertSame($fixture['snapshot']->id, AuditLog::where('tindakan', 'rencana_aksi.buat')->where('objek_id', $header->id)->sole()->nilai_baru['snapshot_draf_id']);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 50, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => 100, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        // v2 menghilangkan B TANPA penyimpanan, lalu v3 mengembalikannya.
        $v2 = $this->terbitkanSnapshotTanpaB($fixture);
        $v3 = $this->terbitkanSnapshotLengkap($fixture, 3, $v2->id);

        // Baca di bawah v3: B tampil kosong (bukan 100 basi), A yang
        // efektif terus-menerus dipertahankan (tanpa kehilangan data).
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', $v3->id)
                ->where('rencanaAksi.expected_snapshot_versi', 3)
                ->where('rencanaAksi.periode.0.nilai.0.komponen_id', $fixture['komponenA']->id)
                ->where('rencanaAksi.periode.0.nilai.0.nilai', '50.000000000000')
                ->where('rencanaAksi.periode.0.nilai.1.komponen_id', $fixture['komponenB']->id)
                ->where('rencanaAksi.periode.0.nilai.1.nilai', null));

        // Simpan tanpa menyentuh B: B basi ikut terbuang (bukan tersimpan
        // ulang dari request), A diperbarui, jepit maju ke v3, teraudit.
        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $v3->id,
            'expected_snapshot_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 55, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => null, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('rencana_aksi_target', [
            'rencana_aksi_id' => $header->id,
            'periode_id' => $fixture['periode1']->id,
            'komponen_id' => $fixture['komponenB']->id,
        ]);
        $this->assertSame('55.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->where('periode_id', $fixture['periode1']->id)
            ->where('komponen_id', $fixture['komponenA']->id)->sole()->getRawOriginal('nilai'));
        $this->assertSame($v3->id, $header->fresh()->snapshot_draf_id);

        $audit = AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->where('alasan', 'like', '%Rekonsiliasi transisi%')->first();
        $this->assertNotNull($audit);
        $this->assertStringContainsString('Rekonsiliasi transisi snapshot v1->v3: 1 baris basi dibersihkan.', (string) $audit->alasan);
        // Perpindahan jepit snapshot ikut terekam agar konteks formula tiap simpan dapat dibuktikan.
        $this->assertSame($fixture['snapshot']->id, $audit->nilai_lama['snapshot_draf_id']);
        $this->assertSame($v3->id, $audit->nilai_baru['snapshot_draf_id']);
    }

    public function test_snapshot_terbit_yang_dipakai_draf_beku_di_db_tapi_koreksi_sisipan_terbuka(): void
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
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        // Mutasi langsung snapshot yang dijepit draf ditolak trigger.
        // Savepoint bersarang memulihkan transaksi uji pasca-abort PG.
        try {
            DB::transaction(function () use ($fixture): void {
                $fixture['snapshot']->update(['target' => 777]);
            });
            $this->fail('Mutasi langsung snapshot yang dipakai draf harus ditolak trigger.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertSame('100.000000000000', $fixture['snapshot']->fresh()->getRawOriginal('target'));

        try {
            DB::transaction(function () use ($fixture): void {
                $fixture['snapshot']->delete();
            });
            $this->fail('Hapus snapshot yang dipakai draf harus ditolak trigger.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        // Koreksi berversi (sisipan v2 + komponennya) tetap terbuka.
        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK T2.',
            'rujukan_koreksi' => 'SK-KOREKSI-R6T2-001',
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
        $this->assertNotNull($v2->id);

        // Simpan di bawah v2 maju — jepit mengikuti, nilai lama utuh.
        $header->refresh();
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => $header->versi,
            'expected_snapshot_id' => $v2->id,
            'expected_snapshot_versi' => 2,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $this->assertSame($v2->id, $header->fresh()->snapshot_draf_id);

        // Beku mengikuti jepit: v2 yang kini dipakai pun tak bisa dimutasi.
        try {
            DB::transaction(function () use ($v2): void {
                $v2->update(['target' => 888]);
            });
            $this->fail('Mutasi snapshot v2 yang kini dipakai draf harus ditolak trigger.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }

    /**
     * Snapshot koreksi v2 penjumlahan yang menghapus komponen B (satu
     * penjumlah tersisa tetap sah untuk kalkulator).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function terbitkanSnapshotTanpaB(array $fixture): JadwalSnapshot
    {
        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi hapus komponen B.',
            'rujukan_koreksi' => 'SK-KOREKSI-R6T2-002',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v2 tanpa komponen B.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
        ]);
        JadwalSnapshotKomponen::create([
            'jadwal_snapshot_id' => $v2->id,
            'komponen_id' => $fixture['komponenA']->id,
            'kode' => 'a',
            'label' => 'Komponen A',
            'peran' => 'penjumlah',
            'bobot' => 1,
            'urutan' => 1,
        ]);

        return $v2;
    }

    /**
     * Snapshot koreksi v3 yang mengembalikan kedua komponen TANPA
     * penyimpanan di bawah v2 (jendela tanpa-simpan).
     *
     * @param  array<string, mixed>  $fixture
     */
    private function terbitkanSnapshotLengkap(array $fixture, int $nomorVersi, string $menggantikanId): JadwalSnapshot
    {
        $snapshot = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => $nomorVersi,
            'menggantikan_id' => $menggantikanId,
            'alasan_koreksi' => 'Koreksi resmi kembalikan komponen B.',
            'rujukan_koreksi' => 'SK-KOREKSI-R6T2-003',
            'periode_mulai_id' => $fixture['periode1']->id,
            'unit_id' => $fixture['unit']->id,
            'nama' => $fixture['indikator']->nama,
            'definisi' => 'Definisi beku v3 lengkap.',
            'satuan' => 'poin',
            'presisi' => 2,
            'desimal_tampilan' => 2,
            'arah' => 'naik_baik',
            'tipe_perhitungan' => 'penjumlahan',
            'target' => 100,
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
     * Fixture penjumlahan dua penjumlah (A+B) agar koreksi penghapusan
     * satu komponen tetap sah untuk kalkulator.
     *
     * @return array<string, mixed>
     */
    private function buatFixturePenjumlahan(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R6T2 Jumlah', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R6T2J', 'nama' => 'Renstra Uji R6T2 Jumlah', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R6T2J', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Jumlah Uji R6T2',
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

    /**
     * @return array<string, mixed>
     */
    private function buatFixtureManual(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R6T2 Manual', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R6T2M', 'nama' => 'Renstra Uji R6T2 Manual', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R6T2M', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Manual Uji R6T2',
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
