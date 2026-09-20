<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerticalSlice1Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $perencanaan;

    protected User $picKelembagaan;

    protected PengukuranKinerja $pengukuran;

    protected function setUp(): void
    {
        parent::setUp();

        // Waktu dan semua relasi dibuat sendiri agar suite tidak bergantung seed development.
        $this->travelTo(Carbon::parse('2026-03-15 09:00:00'));

        $unit = UnitKerja::create([
            'kode' => 'UNIT-UJI',
            'nama' => 'Unit Pengujian',
            'singkatan' => 'UJI',
        ]);

        foreach (['admin', 'perencanaan', 'pegawai', 'pic'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = $this->createUser('admin', $unit);
        $this->perencanaan = $this->createUser('perencanaan', $unit);
        $this->picKelembagaan = $this->createUser('pegawai', $unit);

        $renstra = Renstra::create([
            'kode' => 'RENSTRA-UJI',
            'nama' => 'Renstra Pengujian',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'is_aktif' => true,
        ]);
        $sasaran = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-UJI',
            'deskripsi' => 'Sasaran sintetis untuk pengujian alur existing',
        ]);
        $indikator = IndikatorKinerja::create([
            'sasaran_strategis_id' => $sasaran->id,
            'kode' => 'IND-UJI',
            'nama' => 'Indikator Pengujian',
            'satuan' => '%',
            'tipe_perhitungan' => 'naik_baik',
        ]);
        $penugasan = PenugasanIndikator::create([
            'indikator_kinerja_id' => $indikator->id,
            'unit_kerja_id' => $unit->id,
            'user_id' => $this->picKelembagaan->id,
            'tahun' => 2026,
        ]);
        $periode = PeriodeJadwal::create([
            'tahun' => 2026,
            'triwulan' => 1,
            'nama_periode' => 'Periode Pengujian',
            'tanggal_mulai' => '2026-03-01 00:00:00',
            'tanggal_selesai' => '2026-03-31 23:59:59',
            'status' => 'buka',
        ]);
        $this->pengukuran = PengukuranKinerja::create([
            'penugasan_indikator_id' => $penugasan->id,
            'periode_jadwal_id' => $periode->id,
            'target' => 70.0,
            'status' => 'draft',
        ]);
    }

    private function createUser(string $role, UnitKerja $unit): User
    {
        $user = User::create([
            'name' => 'Pengguna Uji '.$role,
            'email' => $role.'@example.test',
            'password' => 'password-pengujian',
            'unit_kerja_id' => $unit->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Uji Pemisahan Tugas (Separation of Duties):
     * Admin tanpa grant substantif ditolak; kasus ini tidak menetapkan larangan permanen berdasarkan role.
     */
    public function test_admin_without_substantive_grants_cannot_mutate_performance_data(): void
    {
        $response = $this->actingAs($this->admin)->post("/pengukuran/{$this->pengukuran->id}", [
            'realisasi' => 75.0,
            'action' => 'ajukan',
        ]);

        $response->assertStatus(403);

        $ratifyResponse = $this->actingAs($this->admin)->post("/verifikasi/{$this->pengukuran->id}/sahkan");
        $ratifyResponse->assertStatus(403);
    }

    public function test_pic_tanpa_penugasan_aktif_tidak_mendapatkan_akses_pengukuran(): void
    {
        $picTanpaPenugasan = $this->createUser(
            'pic',
            $this->pengukuran->penugasanIndikator->unitKerja,
        );

        $this->actingAs($picTanpaPenugasan)
            ->get('/pengukuran')
            ->assertForbidden();

        $this->actingAs($picTanpaPenugasan)
            ->get("/pengukuran/{$this->pengukuran->id}/edit")
            ->assertForbidden();

        $this->actingAs($picTanpaPenugasan)
            ->post("/pengukuran/{$this->pengukuran->id}", [
                'realisasi' => 85.0,
                'action' => 'draft',
            ])
            ->assertForbidden();
    }

    /**
     * Uji Formula Engine:
     * Kalkulasi otomatis untuk indikator tipe 'naik_baik' dan 'turun_baik'.
     */
    public function test_formula_engine_calculates_naik_baik_and_turun_baik(): void
    {
        // naik_baik: target 70, realisasi 84 => 120%
        $capaianNaik = PengukuranKinerja::hitungCapaian(70.0, 84.0, 'naik_baik');
        $this->assertEquals(120.0, $capaianNaik);

        // naik_baik: target 70, realisasi 35 => 50%
        $capaianNaikKurang = PengukuranKinerja::hitungCapaian(70.0, 35.0, 'naik_baik');
        $this->assertEquals(50.0, $capaianNaikKurang);

        // turun_baik: target 8.0, realisasi 4.0 => ((2*8 - 4)/8)*100 = 150%
        $capaianTurunBaik = PengukuranKinerja::hitungCapaian(8.0, 4.0, 'turun_baik');
        $this->assertEquals(150.0, $capaianTurunBaik);

        // turun_baik: target 8.0, realisasi 10.0 => ((16 - 10)/8)*100 = 75%
        $capaianTurunBuruk = PengukuranKinerja::hitungCapaian(8.0, 10.0, 'turun_baik');
        $this->assertEquals(75.0, $capaianTurunBuruk);
    }

    /**
     * Uji Alur PIC:
     * PIC mengisi realisasi capaian, lampiran bukti, dan mengajukan ke Perencanaan.
     */
    public function test_pic_can_input_realization_and_submit_to_perencanaan(): void
    {
        $response = $this->actingAs($this->picKelembagaan)->post("/pengukuran/{$this->pengukuran->id}", [
            'realisasi' => 85.0,
            'action' => 'ajukan',
            'url_bukti' => 'https://example.test/bukti-tw1.pdf',
            'keterangan_bukti' => 'Laporan Pengujian Triwulan 1',
        ]);

        $response->assertRedirect('/pengukuran');
        $response->assertSessionHas('success');

        $this->pengukuran->refresh();
        $this->assertEquals(85.0, $this->pengukuran->realisasi);
        $this->assertEquals('diajukan', $this->pengukuran->status);
        $this->assertNotNull($this->pengukuran->diajukan_pada);

        // Bukti dukung tersimpan
        $this->assertDatabaseHas('bukti_dukungs', [
            'pengukuran_kinerja_id' => $this->pengukuran->id,
            'url_tautan' => 'https://example.test/bukti-tw1.pdf',
        ]);

        // Riwayat status tercatat
        $this->assertDatabaseHas('riwayat_pengukurans', [
            'pengukuran_kinerja_id' => $this->pengukuran->id,
            'status_ke' => 'diajukan',
        ]);
    }

    /**
     * Uji Alur Perencanaan (Feedback Loop):
     * Tim Perencanaan mengembalikan pengajuan dengan catatan koreksi.
     */
    public function test_perencanaan_can_return_submission_with_mandatory_notes(): void
    {
        // Ubah status ke diajukan terlebih dahulu
        $this->pengukuran->update(['status' => 'diajukan']);

        $response = $this->actingAs($this->perencanaan)->post("/verifikasi/{$this->pengukuran->id}/kembalikan", [
            'catatan' => 'Berkas bukti dukung belum ditandatangani oleh pimpinan yayasan, mohon diperbaiki.',
        ]);

        $response->assertRedirect('/verifikasi');
        $response->assertSessionHas('success');

        $this->pengukuran->refresh();
        $this->assertEquals('dikembalikan', $this->pengukuran->status);

        // Pastikan catatan revisi tersimpan di riwayat
        $this->assertDatabaseHas('riwayat_pengukurans', [
            'pengukuran_kinerja_id' => $this->pengukuran->id,
            'status_ke' => 'dikembalikan',
            'catatan' => 'Berkas bukti dukung belum ditandatangani oleh pimpinan yayasan, mohon diperbaiki.',
        ]);
    }

    /**
     * Uji pengesahan dan pencatatan payload snapshot existing:
     * Tim Perencanaan mengesahkan kinerja dan sistem membekukan data ke format JSONB snapshot dengan SHA256.
     */
    public function test_perencanaan_can_ratify_and_record_snapshot_payload(): void
    {
        // Siapkan pengukuran dalam status diajukan dengan realisasi
        $this->pengukuran->update([
            'status' => 'diajukan',
            'realisasi' => 75.0,
            'capaian_persen' => 107.14,
        ]);

        $response = $this->actingAs($this->perencanaan)->post("/verifikasi/{$this->pengukuran->id}/sahkan");

        $response->assertRedirect('/verifikasi');
        $response->assertSessionHas('success');

        $this->pengukuran->refresh();
        $this->assertEquals('disahkan', $this->pengukuran->status);
        $this->assertEquals($this->perencanaan->id, $this->pengukuran->disahkan_oleh);
        $this->assertNotNull($this->pengukuran->disahkan_pada);

        // Pastikan Snapshot JSONB Tercipta
        $snapshot = KinerjaSnapshot::where('pengukuran_kinerja_id', $this->pengukuran->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertNotEmpty($snapshot->snapshot_hash);
        $this->assertEquals(64, strlen($snapshot->snapshot_hash)); // SHA256 length

        // Verifikasi isi data yang tercatat saat pengesahan
        $data = $snapshot->snapshot_data;
        $this->assertEquals(75.0, $data['realisasi']);
        $this->assertEquals(107.14, $data['capaian_persen']);
        $this->assertEquals($this->perencanaan->name, $data['disahkan_oleh']['name']);
    }
}
