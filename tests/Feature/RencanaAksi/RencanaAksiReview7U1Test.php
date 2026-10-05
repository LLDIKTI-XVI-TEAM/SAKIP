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
 * Regresi Review7 U1 (F1 rekonsiliasi jangan hapus input baru + F2 bekukan sejak dibaca).
 *
 * Keputusan F1: kecualikan dimensi eksplisit terkirim bernilai dalam konteks
 * terbaru (BUKAN purge-sebelum-upsert). Alasan: purge-sebelum menyimpan input
 * baru tetapi meninggalkan baris kosong (null) untuk kiriman yang dikosongkan
 * sehingga menyimpang dari kontrak T2 (baris basi terkirim-kosong dibuang bagai
 * tak ada); pengecualian hanya untuk kiriman bernilai (nilai/keterangan
 * non-null) yang efektif-kini — kiriman kosong tetap dibersihkan, koreksi
 * parsial yang tak terkirim dipertahankan karena tak ada di himpunan basi
 * (hanya basi∩efektif-kini yang dihapus), kiriman basi yang sengaja tak
 * efektif-kini tetap milik `bersihkanDimensiTakEfektif`.
 *
 * Keputusan F2: immutable-sejak-terbit (BUKAN pin-on-read). Alasan: pin-on-read
 * memajukan jepit tanpa membersihkan sehingga bacaan kedua membangkitkan
 * nilai basi (jepit==terbaru → jejak hilang → 100 tampil lagi) dan simpanan
 * parsial berikutnya ikut membangkitkan periode tak terkirim; membersihkan
 * saat baca mengubah GET menjadi destruktif tanpa audit. Immutable menutup
 * jendela mutabel v2 tanpa tulis-di-jalur-baca sehingga token ID+versi selalu
 * mewakili konteks beku yang ditampilkan; koreksi sah tetap via sisipan
 * berversi.
 */
class RencanaAksiReview7U1Test extends TestCase
{
    use RefreshDatabase;

    public function test_f1_input_baru_untuk_dimensi_pulih_tersimpan_dan_parsial_dipertahankan(): void
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
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 30, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => 40, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();
        $header->refresh();
        $this->assertSame(2, $header->versi);

        // v2 menghilangkan B TANPA penyimpanan, lalu v3 mengembalikannya.
        $v2 = $this->terbitkanSnapshotTanpaB($fixture);
        $v3 = $this->terbitkanSnapshotLengkap($fixture, 3, $v2->id);

        // Simpan parsial LANGSUNG di bawah v3 tanpa baca dulu (jepit masih v1):
        // periode1 diisi baru (A=55, B=200 pulih), periode2 tak terkirim.
        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 2,
            'expected_snapshot_id' => $v3->id,
            'expected_snapshot_versi' => 3,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenA']->id, 'nilai' => 55, 'keterangan' => null],
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => $fixture['komponenB']->id, 'nilai' => 200, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $header->refresh();
        $this->assertSame(3, $header->versi);
        $this->assertSame($v3->id, $header->snapshot_draf_id);

        // F1: input baru untuk dimensi pulih tersimpan utuh (bukan terpurge).
        $this->assertSame('55.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->where('periode_id', $fixture['periode1']->id)
            ->where('komponen_id', $fixture['komponenA']->id)->sole()->getRawOriginal('nilai'));
        $this->assertSame('200.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->where('periode_id', $fixture['periode1']->id)
            ->where('komponen_id', $fixture['komponenB']->id)->sole()->getRawOriginal('nilai'));

        // Koreksi parsial: A periode2 tak terkirim (efektif, tak basi) dipertahankan.
        $this->assertSame('30.000000000000', RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->where('periode_id', $fixture['periode2']->id)
            ->where('komponen_id', $fixture['komponenA']->id)->sole()->getRawOriginal('nilai'));

        // Basi transisi periode2 (B=40, tak efektif di v2) ikut terbuang agar
        // tak bangkit sebagai 40 pasca-jepit maju ke v3.
        $this->assertDatabaseMissing('rencana_aksi_target', [
            'rencana_aksi_id' => $header->id,
            'periode_id' => $fixture['periode2']->id,
            'komponen_id' => $fixture['komponenB']->id,
        ]);

        $this->assertTrue(AuditLog::where('tindakan', 'rencana_aksi.ubah')->where('objek_id', $header->id)->exists());
    }

    public function test_f2_snapshot_tampil_beku_sejak_terbit(): void
    {
        $fixture = $this->buatFixtureManual();
        $this->travelTo(now()->setDate(2026, 3, 10)->setTime(9, 0));

        $this->actingAs($fixture['pic'])->post('/rencana-aksi/ensure-draft', [
            'indikator_id' => $fixture['indikator']->id,
            'tahun' => 2026,
        ])->assertSessionHasNoErrors();
        $header = RencanaAksi::where('indikator_id', $fixture['indikator']->id)->sole();
        $this->assertSame($fixture['snapshot']->id, $header->snapshot_draf_id);

        $this->actingAs($fixture['pic'])->post("/rencana-aksi/{$header->id}/target", [
            'expected_versi' => 1,
            'expected_snapshot_id' => $fixture['snapshot']->id,
            'expected_snapshot_versi' => 1,
            'targets' => [
                ['periode_id' => $fixture['periode1']->id, 'komponen_id' => null, 'nilai' => 10, 'keterangan' => null],
                ['periode_id' => $fixture['periode2']->id, 'komponen_id' => null, 'nilai' => 20, 'keterangan' => null],
            ],
        ])->assertSessionHasNoErrors();

        $v2 = JadwalSnapshot::create([
            'jadwal_id' => $fixture['jadwal']->id,
            'indikator_id' => $fixture['indikator']->id,
            'nomor_versi' => 2,
            'menggantikan_id' => $fixture['snapshot']->id,
            'alasan_koreksi' => 'Koreksi resmi target PK U1.',
            'rujukan_koreksi' => 'SK-KOREKSI-R7U1-001',
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

        // Jepit masih v1 sebelum dibaca; v2 beku sejak terbit (immutable,
        // bukan karena dijepit) — mutasi langsung sudah ditolak walau belum
        // ditampilkan.
        $this->assertSame($fixture['snapshot']->id, $header->fresh()->snapshot_draf_id);
        try {
            DB::transaction(function () use ($v2): void {
                $v2->update(['target' => 666]);
            });
            $this->fail('Mutasi snapshot terbit harus ditolak walau belum dijepit.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        // Baca menampilkan v2 (token v2 mewakili konteks beku yang
        // ditampilkan) tanpa menaikkan versi header; jepit tetap v1 sampai
        // save berikutnya (rekonsiliasi transisi tetap utuh, tanpa
        // kebangkitan basi antar-baca).
        $versiSebelum = $header->fresh()->versi;
        $this->actingAs($fixture['pic'])->get("/rencana-aksi/{$header->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rencanaAksi.expected_snapshot_id', $v2->id)
                ->where('rencanaAksi.expected_snapshot_versi', 2));

        $header->refresh();
        $this->assertSame($fixture['snapshot']->id, $header->snapshot_draf_id);
        $this->assertSame($versiSebelum, $header->versi);

        // Mutasi langsung v2 yang tampil ditolak trigger (beku sejak dibaca).
        try {
            DB::transaction(function () use ($v2): void {
                $v2->update(['target' => 777]);
            });
            $this->fail('Mutasi langsung snapshot yang tampil harus ditolak trigger pasca-pin-on-read.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
        $this->assertSame('150.000000000000', $v2->fresh()->getRawOriginal('target'));
    }

    /**
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
            'rujukan_koreksi' => 'SK-KOREKSI-R7U1-002',
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
            'rujukan_koreksi' => 'SK-KOREKSI-R7U1-003',
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
     * @return array<string, mixed>
     */
    private function buatFixturePenjumlahan(): array
    {
        $this->seed(AccessCatalogSeeder::class);
        $perencanaan = $this->penggunaDenganPeran('perencanaan');
        $pic = $this->penggunaDenganPeran('pegawai');
        $unit = Unit::create(['nama' => 'Unit Uji R7U1 Jumlah', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R7U1J', 'nama' => 'Renstra Uji R7U1 Jumlah', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R7U1J', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Jumlah Uji R7U1',
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
        $unit = Unit::create(['nama' => 'Unit Uji R7U1 Manual', 'status' => 'aktif', 'created_by' => $perencanaan->id]);
        $this->grant($pic, 'rencana_aksi:create', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:update', $unit->id, $perencanaan);
        $this->grant($pic, 'rencana_aksi:read', $unit->id, $perencanaan);

        $renstra = Renstra::create(['kode' => 'R-UJI-R7U1M', 'nama' => 'Renstra Uji R7U1 Manual', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $perencanaan->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-UJI-R7U1M', 'deskripsi' => 'Sasaran uji']);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'unit_id' => $unit->id,
            'kode' => 'I-UJI-'.Str::random(4),
            'nama' => 'Indikator Manual Uji R7U1',
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
