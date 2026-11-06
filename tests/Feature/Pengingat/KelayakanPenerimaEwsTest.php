<?php

namespace Tests\Feature\Pengingat;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Pengingat\KandidatPengingatTenggat;
use App\Services\Pengingat\KelayakanPenerimaEws;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Kelayakan penerima EWS diuji pada instan kirim eksplisit. Fixture dasar: jadwal aktif 2026
 * dengan batas pengisian 2026-03-15 dan PIC fixture (`$this->actor`) yang masih punya header draft.
 */
class KelayakanPenerimaEwsTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PeriodeJadwal $tw1;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->tw1 = PeriodeJadwal::where('jadwal_id', $this->jadwal->id)->firstOrFail();
    }

    private function layak(User $user, int $h, string $tanggal = '2026-03-14', ?PeriodeJadwal $periode = null): bool
    {
        return app(KelayakanPenerimaEws::class)->layak(
            $user,
            $periode ?? $this->tw1,
            $h,
            CarbonImmutable::parse($tanggal.' 09:00', 'Asia/Makassar'),
        );
    }

    private function picDenganIndikator(string $kode, User $pic, array $snapshot = []): JadwalSnapshot
    {
        $snapshot = $this->indikatorBaru($kode, [], $snapshot);
        PenugasanIndikator::create(['indikator_id' => $snapshot->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-01-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $snapshot;
    }

    private function grantPada(User $user, string $permission, Unit $unit): void
    {
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => $unit->id,
            'alasan' => 'Fixture pengujian', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    public function test_pic_aktif_berperan_dengan_hak_isi_dan_kewajiban_belum_selesai_layak(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-PIC-UPDATE', $pic);

        $this->assertTrue($this->layak($pic, 1));

        // Gerbang yang sama terbuka lewat `pengukuran:create` (salah satu cukup).
        $picBaru = $this->userWithRole('pegawai');
        $this->grant($picBaru, 'pengukuran:create');
        $this->picDenganIndikator('I-PIC-CREATE', $picBaru);

        $this->assertTrue($this->layak($picBaru, 1));
    }

    public function test_pic_tanpa_grant_hak_isi_tidak_layak(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->picDenganIndikator('I-PIC-TANPA-GRANT', $pic);

        $this->assertFalse($this->layak($pic, 1));
    }

    public function test_pic_nonaktif_tidak_layak_walau_masih_kandidat_evaluator(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-PIC-NONAKTIF', $pic);
        $pic->update(['status' => 'nonaktif']);

        // Kontrol: evaluator kandidat tidak menyaring status akun, sehingga gerbang status di sini yang menolak.
        $kandidat = collect(app(KandidatPengingatTenggat::class)->untukTanggal(
            CarbonImmutable::parse('2026-03-14 09:00', 'Asia/Makassar'),
            [1],
        )['kandidat']);
        $this->assertNotNull($kandidat->firstWhere('user_id', $pic->id));

        $this->assertFalse($this->layak($pic, 1));
    }

    public function test_status_akun_dibaca_ulang_bukan_dari_model_lama(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-PIC-MODEL-BASI', $pic);

        // Instance terpisah sengaja tidak disegarkan setelah status diubah lewat query.
        $basi = User::findOrFail($pic->id);
        User::whereKey($pic->id)->update(['status' => 'nonaktif']);

        $this->assertSame('aktif', $basi->status);
        $this->assertFalse($this->layak($basi, 1));
    }

    public function test_pic_tanpa_peran_aktif_tidak_layak(): void
    {
        $tanpaPeran = User::factory()->create(['status' => 'aktif']);
        $this->grant($tanpaPeran, 'pengukuran:update');
        $this->picDenganIndikator('I-TANPA-PERAN', $tanpaPeran);

        $this->assertFalse($this->layak($tanpaPeran, 1));

        $peranNonaktif = $this->userWithRole('pegawai');
        $this->grant($peranNonaktif, 'pengukuran:update');
        $this->picDenganIndikator('I-PERAN-NONAKTIF', $peranNonaktif);
        Role::where('kode', 'pegawai')->update(['aktif' => false]);

        $this->assertFalse($this->layak($peranNonaktif, 1));
    }

    public function test_header_diajukan_diverifikasi_disahkan_tidak_layak(): void
    {
        foreach (['diajukan', 'diverifikasi', 'disahkan'] as $status) {
            $this->pengukuran->update(['status_alur' => $status]);

            $this->assertFalse($this->layak($this->actor, 1), "Header $status seharusnya tuntas.");
        }
    }

    public function test_header_dikembalikan_tetap_layak(): void
    {
        $this->pengukuran->update(['status_alur' => 'dikembalikan']);

        $this->assertTrue($this->layak($this->actor, 1));
    }

    public function test_h_yang_diminta_harus_sama_dengan_tenggat_berjalan(): void
    {
        $this->assertTrue($this->layak($this->actor, 1));

        $this->assertFalse($this->layak($this->actor, 7), 'Minta H-7 saat tinggal 1 hari.');
        $this->assertFalse($this->layak($this->actor, 3));
        $this->assertFalse($this->layak($this->actor, 0));

        // Pada hari tenggat H-0 sah, dan permintaan H-1 lama gagal tertutup.
        $this->assertTrue($this->layak($this->actor, 0, '2026-03-15'));
        $this->assertFalse($this->layak($this->actor, 1, '2026-03-15'));
    }

    public function test_periode_lampau_2026_tidak_layak(): void
    {
        $lampau = $this->tambahPeriode(2, '2026-02-01', '2026-03-10');
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-PERIODE-LAMPAU', $pic, ['periode_mulai_id' => $lampau->periode_id]);

        $this->assertFalse($this->layak($pic, 7, '2026-03-03', $lampau));
    }

    public function test_jadwal_ditutup_atau_tahun_ditutup_tidak_layak(): void
    {
        $this->jadwal->update(['status' => 'ditutup']);
        $this->assertFalse($this->layak($this->actor, 1));

        $this->jadwal->update(['status' => 'aktif', 'penutupan' => '2026-03-10']);
        $this->assertFalse($this->layak($this->actor, 1));
    }

    public function test_unit_nonaktif_dan_indikator_arsip_tidak_layak(): void
    {
        $unitNonaktif = $this->unitBaru('Unit Nonaktif');
        $picUnit = $this->userWithRole('pegawai');
        // Grant diletakkan pada unit itu sendiri agar penolakan murni berasal dari status unit/snapshot.
        $this->grantPada($picUnit, 'pengukuran:update', $unitNonaktif);
        $this->picDenganIndikator('I-UNIT-NONAKTIF', $picUnit, ['unit_id' => $unitNonaktif->id]);
        $unitNonaktif->update(['status' => 'nonaktif']);

        $this->assertFalse($this->layak($picUnit, 1));

        $picArsip = $this->userWithRole('pegawai');
        $this->grant($picArsip, 'pengukuran:update');
        $arsip = $this->picDenganIndikator('I-ARSIP', $picArsip);
        IndikatorKinerja::whereKey($arsip->indikator_id)->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

        $this->assertFalse($this->layak($picArsip, 1));
    }

    public function test_hak_isi_dinilai_pada_unit_indikator_kandidat_bukan_unit_lain(): void
    {
        $unitLain = $this->unitBaru('Unit Lain');
        $pic = $this->userWithRole('pegawai');
        $this->grantPada($pic, 'pengukuran:update', $unitLain);
        // Kewajiban belum selesai hanya ada pada unit tanpa grant.
        $this->picDenganIndikator('I-UNIT-TANPA-GRANT', $pic);

        $this->assertFalse($this->layak($pic, 1));
    }

    public function test_satu_unit_berizin_di_antara_kewajiban_cukup_untuk_layak(): void
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        $this->picDenganIndikator('I-UNIT-BERIZIN', $pic);
        $this->picDenganIndikator('I-UNIT-TANPA-IZIN', $pic, ['unit_id' => $this->unitBaru('Unit Tanpa Izin')->id]);

        $this->assertTrue($this->layak($pic, 1));
    }

    public function test_service_tidak_menulis_baris_apa_pun(): void
    {
        $tabel = ['pengukuran_kinerjas', 'jadwal_periode', 'penanggung_jawab', 'user_permission_granted',
            'user_permission_denied', 'audit_log', 'jobs', 'failed_jobs'];
        $sebelum = [];
        foreach ($tabel as $nama) {
            $sebelum[$nama] = DB::table($nama)->count();
        }

        $this->assertTrue($this->layak($this->actor, 1));
        $this->assertFalse($this->layak(User::factory()->create(['status' => 'aktif']), 1));

        foreach ($tabel as $nama) {
            $this->assertSame($sebelum[$nama], DB::table($nama)->count(), "Tabel $nama berubah.");
        }
    }
}
