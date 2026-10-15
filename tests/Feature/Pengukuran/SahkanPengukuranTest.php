<?php

namespace Tests\Feature\Pengukuran;

use App\Actions\Access\AssignRole;
use App\Models\AuditLog;
use App\Models\PenugasanIndikator;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * ISS-08.02: pengesahan pengukuran diverifikasi → disahkan dengan F1/F2.
 * Memakai fixture sintetis agar provenance, versi beku, dan audit terbukti
 * tanpa bergantung data development.
 */
class SahkanPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function siapkanPic(): User
    {
        $pic = $this->userWithRole('pegawai');
        $this->grant($pic, 'pengukuran:update');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pic->id,
            'tanggal_mulai_berlaku' => '2026-03-01', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        return $pic;
    }

    private function ajukan(User $pengaju, array $tambahan = []): void
    {
        $this->actingAs($pengaju)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => $this->pengukuran->fresh()->versi, 'action' => 'ajukan', 'nilai' => 85, ...$tambahan])
            ->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $this->pengukuran->refresh();
    }

    private function reviu(User $aktor, string $perintah): TestResponse
    {
        $respons = $this->actingAs($aktor)->post('/verifikasi/'.$this->pengukuran->id.'/'.$perintah,
            ['versi' => $this->pengukuran->fresh()->versi]);
        $this->pengukuran->refresh();

        return $respons;
    }

    private function tolak(User $pengguna, string $kode): void
    {
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pengguna->id,
            'permission_id' => Permission::where('kode', $kode)->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pencabutan pengujian',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
    }

    private function assertBelumDisahkan(): void
    {
        $header = $this->pengukuran->fresh();
        $this->assertSame('diverifikasi', $header->status_alur);
        $this->assertNull($header->latestVersion->disahkan_by);
        $this->assertNull($header->latestVersion->disahkan_at);
    }

    public function test_f1_menolak_pengesahan_oleh_pengaju_jalur_pic(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $this->assertSame('pic', $this->pengukuran->latestVersion->jalur_pengajuan);
        $penelaah = $this->userWithRole('perencanaan');
        $this->reviu($penelaah, 'verifikasi')->assertSessionHasNoErrors();

        // Perubahan peran setelah submit tidak menghapus provenance F1.
        $token = (array) DB::table('user_roles')->where('user_id', $pic->id)->first(['id', 'role_id', 'audit_id']);
        app(AssignRole::class)->handle($this->actor, $pic->id, Role::where('kode', 'perencanaan')->value('id'), 'Penyesuaian peran pengujian F1', $token);

        $this->reviu($pic, 'sahkan')->assertSessionHasErrors('versi');
        $this->assertSame('Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.', session('errors')->first('versi'));

        $this->assertBelumDisahkan();
        $this->assertSame($pic->id, $this->pengukuran->latestVersion->diajukan_by);
        $this->assertDatabaseHas('audit_log', ['objek_id' => $this->pengukuran->id, 'tindakan' => 'pengukuran.ditolak']);
    }

    public function test_perencana_lain_mengesahkan_pengajuan_pic_secara_atomik(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $penelaah = $this->userWithRole('perencanaan');
        $this->reviu($penelaah, 'verifikasi')->assertSessionHasNoErrors();
        $beku = $this->pengukuran->latestVersion->getRawOriginal('snapshot');
        $pengesah = $this->userWithRole('perencanaan');

        $this->reviu($pengesah, 'sahkan')->assertSessionHasNoErrors()->assertRedirect('/verifikasi');

        $header = $this->pengukuran->fresh();
        $versi = $header->latestVersion;
        $this->assertSame('disahkan', $header->status_alur);
        $this->assertSame($pengesah->id, $versi->disahkan_by);
        $this->assertNotNull($versi->disahkan_at);
        $this->assertSame($beku, $versi->getRawOriginal('snapshot'));
        $audit = AuditLog::where('tindakan', 'pengukuran.sahkan')->sole();
        $this->assertFalse($audit->nilai_baru['self_approval']);
        $this->assertNotEmpty($audit->dasar_izin);
        $this->assertDatabaseHas('riwayat_pengukurans', ['pengukuran_kinerja_id' => $header->id,
            'status_dari' => 'diverifikasi', 'status_ke' => 'disahkan']);
    }

    public function test_f2_mengizinkan_self_approval_jalur_perencanaan_dan_menandainya(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $this->ajukan($perencana);
        $this->assertSame('perencanaan', $this->pengukuran->latestVersion->jalur_pengajuan);

        $this->reviu($perencana, 'verifikasi')->assertSessionHasNoErrors();
        $this->reviu($perencana, 'sahkan')->assertSessionHasNoErrors()->assertRedirect('/verifikasi');

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $audit = AuditLog::where('tindakan', 'pengukuran.sahkan')->sole();
        $this->assertTrue($audit->nilai_baru['self_approval']);
        $this->assertNotEmpty($audit->dasar_izin);
    }

    public function test_f2_tidak_melewati_deny_atau_izin_yang_hilang(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $this->ajukan($perencana);
        $this->reviu($perencana, 'verifikasi')->assertSessionHasNoErrors();

        $this->tolak($perencana, 'pengukuran:sahkan');
        $this->reviu($perencana, 'sahkan')->assertForbidden();
        $this->assertBelumDisahkan();

        $tanpaIzin = $this->userWithRole('pegawai');
        $this->reviu($tanpaIzin, 'sahkan')->assertForbidden();
        $this->assertBelumDisahkan();
    }

    public function test_versi_stale_ditolak_tanpa_mutasi_ganda(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $penelaah = $this->userWithRole('perencanaan');
        $this->reviu($penelaah, 'verifikasi')->assertSessionHasNoErrors();
        $usang = $this->pengukuran->versi - 1;

        $this->actingAs($penelaah)->post('/verifikasi/'.$this->pengukuran->id.'/sahkan', ['versi' => $usang])
            ->assertSessionHasErrors('versi');

        $this->assertBelumDisahkan();
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    /**
     * Kontrak snapshot untuk laporan resmi (AC-6 menunggu ISS-10.05): bacaan
     * resmi yang dibekukan tidak mengikuti perubahan master belakangan.
     */
    public function test_snapshot_beku_tetap_dibaca_setelah_master_berubah(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $pengesah = $this->userWithRole('perencanaan');
        $this->reviu($pengesah, 'verifikasi')->assertSessionHasNoErrors();
        $this->reviu($pengesah, 'sahkan')->assertSessionHasNoErrors();

        $versi = $this->pengukuran->fresh()->latestVersion;
        $this->assertNotNull($versi->disahkan_at);
        $this->pengukuran->indikator->update(['nama' => 'Master berubah setelah sahkan']);

        $this->assertSame('Indikator Uji', $versi->fresh()->snapshot['indikator']['nama']);
        $this->assertSame(85.0, (float) $versi->fresh()->snapshot['nilai']);
        $this->actingAs($pengesah)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()
            ->assertInertia(fn ($halaman) => $halaman->where('pengukuran.status', 'disahkan')
                ->where('pengukuran.penugasan_indikator.indikator_kinerja.nama', 'Indikator Uji'));
    }

    public function test_indikator_arsip_menolak_pengesahan_termasuk_jalur_f2(): void
    {
        $perencana = $this->userWithRole('perencanaan');
        $this->ajukan($perencana);
        $this->reviu($perencana, 'verifikasi')->assertSessionHasNoErrors();
        $this->pengukuran->indikator->update(['status' => 'arsip']);

        $this->reviu($perencana, 'sahkan')->assertSessionHasErrors('versi');

        $this->assertBelumDisahkan();
        // Q37 §37.1 butir 3: UI menerima capability beserta alasan bisnis indikator arsip.
        $pesanArsip = 'Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.';
        $this->actingAs($perencana)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()
            ->assertInertia(fn ($halaman) => $halaman->where('pengukuran.status', 'diverifikasi')
                ->where('pengukuran.businessErrors.sahkan', [$pesanArsip]));
    }

    public function test_pembuat_draft_bukan_pengaju_tidak_terkena_f1(): void
    {
        // Pembuat memakai jalur yang sah sehingga draf benar-benar tersimpan.
        // created_by disamakan agar test gagal bila F1 keliru memakai pembuat header.
        $pembuat = $this->userWithRole('perencanaan');
        $this->pengukuran->update(['created_by' => $pembuat->id]);
        $this->actingAs($pembuat)->post('/pengukuran/'.$this->pengukuran->id,
            ['versi' => 1, 'action' => 'draft', 'nilai' => 70])
            ->assertSessionHasNoErrors()->assertRedirect('/pengukuran');
        $this->assertSame('70.000000000000', $this->pengukuran->fresh()->nilai);
        $this->assertSame($pembuat->id, AuditLog::where('tindakan', 'pengukuran.draft')->sole()->actor_id);

        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $this->assertSame($pic->id, $this->pengukuran->latestVersion->diajukan_by);

        // Pembuat draf (bukan pengaju) boleh mengesahkan dan berhasil; F1 memakai diajukan_by.
        $penelaah = $this->userWithRole('perencanaan');
        $this->reviu($penelaah, 'verifikasi')->assertSessionHasNoErrors();
        $this->reviu($pembuat, 'sahkan')->assertSessionHasNoErrors()->assertRedirect('/verifikasi');

        $this->assertSame('disahkan', $this->pengukuran->fresh()->status_alur);
        $audit = AuditLog::where('tindakan', 'pengukuran.sahkan')->sole();
        $this->assertSame($pembuat->id, $audit->actor_id);
        $this->assertFalse($audit->nilai_baru['self_approval']);
    }

    public function test_pergantian_pic_setelah_submit_tidak_menghapus_f1(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $token = (array) DB::table('user_roles')->where('user_id', $pic->id)->first(['id', 'role_id', 'audit_id']);
        app(AssignRole::class)->handle($this->actor, $pic->id, Role::where('kode', 'perencanaan')->value('id'), 'Penyesuaian peran pengujian F1', $token);
        $pengganti = $this->userWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pengganti->id,
            'tanggal_mulai_berlaku' => '2026-03-10', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $penelaah = $this->userWithRole('perencanaan');
        $this->reviu($penelaah, 'verifikasi')->assertSessionHasNoErrors();
        // Grant PIC dicabut agar test gagal bila F1 keliru bergantung pada grant berjalan.
        DB::table('user_permission_granted')->where('user_id', $pic->id)->delete();
        $this->reviu($pic, 'sahkan')->assertSessionHasErrors('versi');
        $this->assertSame('Pengaju jalur PIC tidak boleh menyetujui pengajuannya sendiri.', session('errors')->first('versi'));

        $this->assertBelumDisahkan();
        $this->assertSame($pic->id, $this->pengukuran->latestVersion->diajukan_by);
        $this->assertSame($pengganti->id, $this->pengukuran->fresh()->effectivePic()->user_id);
    }

    public function test_indikator_arsip_menolak_simpan_draf_dan_pengajuan_jalur_pic(): void
    {
        $pic = $this->siapkanPic();
        $this->pengukuran->indikator->update(['status' => 'arsip']);
        $pesan = 'Indikator berstatus arsip; pengukuran tidak dapat diubah, diajukan, atau direviu.';

        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'draft', 'nilai' => 70])
            ->assertSessionHasErrors('versi');
        $this->assertSame($pesan, session('errors')->first('versi'));
        $this->actingAs($pic)->post('/pengukuran/'.$this->pengukuran->id, ['versi' => 1, 'action' => 'ajukan', 'nilai' => 70])
            ->assertSessionHasErrors('versi');

        $header = $this->pengukuran->fresh();
        $this->assertSame('draft', $header->status_alur);
        $this->assertSame(1, $header->versi);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public function test_indikator_arsip_menolak_verifikasi_dan_pengembalian(): void
    {
        $pic = $this->siapkanPic();
        $this->ajukan($pic);
        $this->pengukuran->indikator->update(['status' => 'arsip']);
        $penelaah = $this->userWithRole('perencanaan');

        $this->reviu($penelaah, 'verifikasi')->assertSessionHasErrors('versi');
        $this->actingAs($penelaah)->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan',
            ['versi' => $this->pengukuran->fresh()->versi, 'catatan' => 'Lengkapi bukti dukung.'])
            ->assertSessionHasErrors('versi');

        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame(2, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
        $this->actingAs($penelaah)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()
            ->assertInertia(fn ($halaman) => $halaman->where('pengukuran.status', 'diajukan'));
    }
}
