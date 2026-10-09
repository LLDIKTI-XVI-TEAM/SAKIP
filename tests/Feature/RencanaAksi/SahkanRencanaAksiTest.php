<?php

namespace Tests\Feature\RencanaAksi;

use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\Role;
use App\Models\SasaranStrategis;
use App\Models\Unit;
use App\Models\User;
use App\Support\PermissionCodes;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ISS-05.05: Pengesahan Rencana Aksi diverifikasi→disahkan + F1/F2 + stale + audit + guard bukti pasca-sah.
 * Fixture terisolasi: langsung membuat RA diverifikasi + versi beku, tanpa memakai ulang trait global.
 */
class SahkanRencanaAksiTest extends TestCase
{
    use RefreshDatabase;

    private User $perencana;

    private User $picUser;

    private Unit $unit;

    private RencanaAksi $ra;

    private JadwalSnapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->perencana = $this->userWithRole('perencanaan');
        $this->picUser = $this->userWithRole('pegawai');
        $superadmin = $this->userWithRole('superadmin');
        $this->unit = Unit::create(['nama' => 'Unit Pengujian RA', 'created_by' => $superadmin->id]);
        $renstra = Renstra::create(['kode' => 'R-RA', 'nama' => 'Renstra RA', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_aktif' => true, 'created_by' => $superadmin->id]);
        $sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-RA', 'deskripsi' => 'Sasaran RA']);
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $sasaran->id, 'unit_id' => $this->unit->id, 'kode' => 'I-RA', 'nama' => 'Indikator RA',
            'satuan' => 'poin', 'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $superadmin->id, 'created_by_role' => 'superadmin']);
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'nomor_pk' => 'PK-RA', 'tanggal_pk' => '2026-01-01', 'created_by' => $superadmin->id]);
        $periode = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'renstra_pk_id' => $pk->id, 'penutupan' => '2026-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        $this->snapshot = JadwalSnapshot::create(['jadwal_id' => $jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $periode->id, 'unit_id' => $this->unit->id,
            'nama' => 'Indikator RA', 'definisi' => 'Definisi operasional beku.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        // PIC operasional memperoleh hak kerja unit lewat grant scoped; Perencanaan memegang allow global via role.
        $this->grant($this->picUser, 'rencana_aksi:ajukan');
        $this->ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $jadwal->id,
            'penanggung_jawab_id' => $this->picUser->id, 'created_by' => $superadmin->id, 'status_alur' => 'diverifikasi']);
    }

    /** Versi pengajuan beku sejak INSERT; provenance tiap test dibuat langsung, bukan diubah. */
    private function ajukanVersi(string $jalur, User $diajukanBy): void
    {
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->ra->id, 'jadwal_snapshot_id' => $this->snapshot->id, 'nomor' => 1, 'diajukan_by' => $diajukanBy->id,
            'diajukan_at' => now(), 'jalur_pengajuan' => $jalur, 'dasar_izin_pengajuan' => ['jalur' => $jalur, 'unit_id' => $this->unit->id], 'snapshot' => ['uraian' => 'Versi pengajuan beku.']]);
    }

    public function test_1_f1_menolak_pengesahan_pengaju_jalur_pic(): void
    {
        $reviewer = $this->userWithRole('perencanaan');
        // Provenance beku: pengaju jalur PIC adalah reviewer sendiri.
        $this->ajukanVersi('pic', $reviewer);

        $response = Gate::forUser($reviewer)->inspect('sahkan', $this->ra->fresh());

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('jalur PIC', (string) $response->message());

        $this->actingAs($reviewer)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasErrors('versi');
        $this->assertSame('diverifikasi', $this->ra->fresh()->status_alur);
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'rencana_aksi.ditolak', 'objek_tipe' => 'rencana_aksi', 'objek_id' => $this->ra->id]);
    }

    public function test_2_perencana_lain_mengesahkan_pengajuan_pic_secara_atomik(): void
    {
        $this->ajukanVersi('pic', $this->picUser);

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasNoErrors();

        $ra = $this->ra->fresh();
        $version = $ra->latestVersion;
        $this->assertSame('disahkan', $ra->status_alur);
        $this->assertSame(2, $ra->versi);
        $this->assertSame($this->perencana->id, $version->disahkan_by);
        $this->assertNotNull($version->disahkan_at);
        $this->assertSame($this->perencana->id, $ra->disahkan_by);
        $this->assertNotNull($ra->disahkan_at);
        $audit = DB::table('audit_log')->where('tindakan', 'rencana_aksi.sahkan')->where('objek_id', $ra->id)->firstOrFail();
        $basisIzin = json_decode((string) $audit->dasar_izin, true);
        $this->assertSame(PermissionCodes::RENCANA_AKSI_SAHKAN, $basisIzin['permission']);
        $this->assertSame('diizinkan', $basisIzin['keputusan']);
        $this->assertSame('allow', $basisIzin['alasan']);
        $nilaiBaru = json_decode((string) $audit->nilai_baru, true);
        $this->assertFalse($nilaiBaru['self_approval']);
        $this->assertSame('disahkan', $nilaiBaru['status_alur']);
    }

    public function test_3_f2_mengizinkan_self_approval_jalur_perencanaan_dengan_catatan_audit(): void
    {
        $this->ajukanVersi('perencanaan', $this->perencana);

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasNoErrors();

        $this->assertSame('disahkan', $this->ra->fresh()->status_alur);
        $audit = DB::table('audit_log')->where('tindakan', 'rencana_aksi.sahkan')->where('objek_id', $this->ra->id)->firstOrFail();
        $this->assertTrue(json_decode((string) $audit->nilai_baru, true)['self_approval']);
    }

    public function test_4_f2_tidak_melewati_deny_atau_permission_hilang(): void
    {
        // Jalur perencanaan + self-approval, tetapi deny eksplisit tetap menang atas F2.
        $this->ajukanVersi('perencanaan', $this->perencana);
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:sahkan')->value('id'),
            'unit_id' => null, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);

        $this->assertTrue(Gate::forUser($this->perencana)->inspect('sahkan', $this->ra->fresh())->denied());
        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertForbidden();
        $this->assertSame('diverifikasi', $this->ra->fresh()->status_alur);

        // Aktor tanpa permission sahkan juga ditolak walau bukan pengaju.
        $pegawai = $this->userWithRole('pegawai');
        $this->actingAs($pegawai)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertForbidden();
    }

    public function test_5_versi_terbaru_merujuk_snapshot_tidak_selaras_ditolak(): void
    {
        $this->ajukanVersi('pic', $this->picUser);
        $otherUnit = Unit::create(['nama' => 'Unit Lain', 'created_by' => $this->perencana->id]);
        $otherSnapshot = JadwalSnapshot::create(['jadwal_id' => $this->snapshot->jadwal_id, 'indikator_id' => $this->ra->indikator_id,
            'periode_mulai_id' => $this->snapshot->periode_mulai_id, 'unit_id' => $otherUnit->id, 'nama' => 'Indikator RA',
            'definisi' => 'Definisi operasional beku.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70, 'nomor_versi' => 2]);
        // Versi terbaru (nomor 2) merujuk snapshot unit berbeda: konteks beku tidak
        // selaras dengan header sehingga pengesahan ditolak.
        RencanaAksiVersi::create(['rencana_aksi_id' => $this->ra->id, 'jadwal_snapshot_id' => $otherSnapshot->id, 'nomor' => 2,
            'diajukan_by' => $this->picUser->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic',
            'dasar_izin_pengajuan' => ['jalur' => 'pic', 'unit_id' => $this->unit->id], 'snapshot' => ['uraian' => 'Versi kedua beku.']]);

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasErrors('versi');
        $this->assertSame('diverifikasi', $this->ra->fresh()->status_alur);
        $this->assertNull($this->ra->fresh()->latestVersion->disahkan_at);
    }

    /**
     * Guard jadwal: header menunjuk jadwal B sementara snapshot beku berasal
     * dari jadwal A → sahkan ditolak sebelum evaluasi penutupan/koreksi.
     */
    public function test_7_header_jadwal_tidak_cocok_snapshot_ditolak(): void
    {
        $this->ajukanVersi('pic', $this->picUser);
        $jadwalA = JadwalTahunan::findOrFail($this->ra->jadwal_tahunan_id);
        // Jadwal B valid (aktif, belum tutup) tetapi berbeda dari snapshot beku;
        // tahun berbeda agar tidak melanggar unique (renstra_id, tahun).
        $jadwalB = JadwalTahunan::create(['renstra_id' => $jadwalA->renstra_id, 'tahun' => 2027, 'renstra_pk_id' => $jadwalA->renstra_pk_id,
            'penutupan' => '2027-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        $this->ra->update(['jadwal_tahunan_id' => $jadwalB->id]);

        $denied = Gate::forUser($this->perencana)->inspect('sahkan', $this->ra->fresh());
        $this->assertTrue($denied->denied());
        $this->assertStringContainsString('tidak cocok', (string) $denied->message());

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasErrors('versi');
        $this->assertSame('diverifikasi', $this->ra->fresh()->status_alur);
        $this->assertNull($this->ra->fresh()->latestVersion->disahkan_at);
        $this->assertDatabaseHas('audit_log', ['tindakan' => 'rencana_aksi.ditolak', 'objek_tipe' => 'rencana_aksi', 'objek_id' => $this->ra->id]);
    }

    public function test_6_bukti_rujukan_versi_resmi_tidak_boleh_dihapus(): void
    {
        $this->ajukanVersi('pic', $this->picUser);
        $bukti = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $this->ra->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti rujukan versi resmi.', 'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        // Sebelum sah: pemegang berkas:delete masih boleh menghapus.
        $this->assertTrue(Gate::forUser($this->perencana)->inspect('deleteEvidence', [$this->ra->fresh(), $bukti])->allowed());

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasNoErrors();

        $denied = Gate::forUser($this->perencana)->inspect('deleteEvidence', [$this->ra->fresh(), $bukti]);
        $this->assertTrue($denied->denied());
        $this->assertStringContainsString('tidak boleh dihapus', (string) $denied->message());
    }

    public function test_regression_provenance_beku_setelah_perubahan_role(): void
    {
        $this->ajukanVersi('pic', $this->picUser);
        // Pembuat draft (created_by) berbeda dari pengaju; F1 memakai diajukan_by + jalur beku.
        $drafter = $this->userWithRole('pegawai');
        $this->ra->update(['created_by' => $drafter->id]);
        // Perubahan role setelah submit (satu role per user): pengaju jalur PIC menjadi Perencanaan.
        DB::table('user_roles')->where('user_id', $this->picUser->id)->update(['role_id' => Role::where('kode', 'perencanaan')->firstOrFail()->id]);

        $version = $this->ra->fresh()->latestVersion;
        $this->assertSame('pic', $version->jalur_pengajuan);
        $this->assertSame($this->picUser->id, $version->diajukan_by);
        $this->assertTrue(Gate::forUser($this->picUser)->inspect('sahkan', $this->ra->fresh())->denied());
    }

    public function test_stale_concurrency_pengesahan_kedua_ditolak(): void
    {
        $this->ajukanVersi('pic', $this->picUser);
        $reviewerKedua = $this->userWithRole('perencanaan');

        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])->assertSessionHasNoErrors();
        $this->actingAs($reviewerKedua)->post('/rencana-aksi/'.$this->ra->id.'/sahkan', ['versi' => 1])
            ->assertSessionHasErrors('versi', 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.');

        $ra = $this->ra->fresh();
        $this->assertSame('disahkan', $ra->status_alur);
        $this->assertSame($this->perencana->id, $ra->latestVersion->disahkan_by);
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }

    private function grant(User $user, string $permission): void
    {
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Fixture pengujian', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
    }
}
