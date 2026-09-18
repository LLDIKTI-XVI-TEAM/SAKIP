<?php

namespace Tests\Feature;

use App\Models\IndikatorKinerja;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\RiwayatPengukuran;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VerticalSlice1Test extends TestCase
{
    use DatabaseTransactions;

    protected User $superadmin;
    protected User $admin;
    protected User $perencanaan;
    protected User $picKelembagaan;
    protected User $picAkademik;
    protected PengukuranKinerja $pengukuran;
    protected PeriodeJadwal $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::where('email', 'superadmin@lldikti16.kemdikbud.go.id')->first();
        $this->admin = User::where('email', 'admin@lldikti16.kemdikbud.go.id')->first();
        $this->perencanaan = User::where('email', 'perencanaan@lldikti16.kemdikbud.go.id')->first();
        $this->picKelembagaan = User::where('email', 'pic.kelembagaan@lldikti16.kemdikbud.go.id')->first();
        $this->picAkademik = User::where('email', 'pic.akademik@lldikti16.kemdikbud.go.id')->first();

        $this->periode = PeriodeJadwal::where('triwulan', 1)->where('tahun', 2026)->first();
        $this->pengukuran = PengukuranKinerja::first();
    }

    /**
     * Uji Pemisahan Tugas (Separation of Duties):
     * Admin TI DILARANG memanipulasi atau mengesahkan data kinerja substansi SAKIP.
     */
    public function test_admin_is_strictly_forbidden_from_manipulating_performance_data(): void
    {
        $response = $this->actingAs($this->admin)->post("/pengukuran/{$this->pengukuran->id}", [
            'realisasi' => 75.0,
            'action' => 'ajukan',
        ]);

        $response->assertStatus(403);

        $ratifyResponse = $this->actingAs($this->admin)->post("/verifikasi/{$this->pengukuran->id}/sahkan");
        $ratifyResponse->assertStatus(403);
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
            'url_bukti' => 'https://drive.google.com/lldikti16/bukti-tw1.pdf',
            'keterangan_bukti' => 'Laporan Hasil Akreditasi BAN-PT Triwulan 1',
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
            'url_tautan' => 'https://drive.google.com/lldikti16/bukti-tw1.pdf',
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
     * Uji Alur Pengesahan Resmi & Immutability Snapshot:
     * Tim Perencanaan mengesahkan kinerja dan sistem membekukan data ke format JSONB snapshot dengan SHA256.
     */
    public function test_perencanaan_can_ratify_and_create_immutable_snapshot(): void
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

        // Verifikasi isi data snapshot imutabel
        $data = $snapshot->snapshot_data;
        $this->assertEquals(75.0, $data['realisasi']);
        $this->assertEquals(107.14, $data['capaian_persen']);
        $this->assertEquals($this->perencanaan->name, $data['disahkan_oleh']['name']);
    }
}
