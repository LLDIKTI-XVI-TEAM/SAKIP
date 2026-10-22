<?php

namespace Tests\Feature\Kegiatan;

use App\Actions\Kegiatan\DaftarKegiatan;
use App\Actions\Kegiatan\SimpanKegiatan;
use App\Models\AuditLog;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Renstra;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionDeny;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class KegiatanHttpTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private User $perencanaan;

    private User $pegawai;

    private Unit $unit;

    private Unit $unitLain;

    private Periode $periode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->perencanaan = $this->user('perencanaan');
        $this->pegawai = $this->user('pegawai');
        $this->unit = Unit::create(['nama' => 'Unit A', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);
        $this->unitLain = Unit::create(['nama' => 'Unit B', 'status' => 'aktif', 'created_by' => $this->perencanaan->id]);
        $this->periode = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Penugasan kegiatan unit', 'diberikan_oleh' => $this->perencanaan->id]);
        }
    }

    public function test_pegawai_tanpa_pj_dan_jadwal_membuat_rencana_dengan_audit_dan_tanpa_input_fase_lanjut(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['tanggal_rencana' => '2026-10-17']) + [
            'status' => 'terlaksana', 'created_by' => $this->perencanaan->id,
            'tanggal_realisasi' => '2026-10-12', 'uraian_pelaksanaan' => 'Tidak boleh tersimpan',
        ])->assertRedirect('/kegiatan?tahun=2026')->assertSessionHasNoErrors();

        $kegiatan = Kegiatan::sole();
        $this->assertSame('rencana', $kegiatan->status);
        $this->assertSame($this->pegawai->id, $kegiatan->created_by);
        $this->assertSame('1500000', $kegiatan->anggaran);
        $this->assertNull($kegiatan->tanggal_realisasi);
        $this->assertNull($kegiatan->uraian_pelaksanaan);
        $this->assertSame('2026-10-17', $kegiatan->tanggal_rencana->format('Y-m-d'));
        $audit = AuditLog::where('tindakan', 'kegiatan.buat')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('rencana', $audit->nilai_baru['status']);
        $this->assertNotEmpty($audit->dasar_izin);
        $this->assertDatabaseCount('penanggung_jawab', 0);
        $this->assertDatabaseCount('jadwal_tahunan', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function anggaranTidakSah(): array
    {
        return ['kosong' => [null], 'negatif' => [-1], 'pecahan sen' => ['1500.50'], 'bukan angka' => ['satu juta'], 'lebih dari 15 digit' => ['1000000000000000']];
    }

    #[DataProvider('anggaranTidakSah')]
    public function test_anggaran_wajib_rupiah_bulat_nonnegatif(mixed $anggaran): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['anggaran' => $anggaran]))->assertSessionHasErrors('anggaran');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_basis_data_menolak_anggaran_negatif_di_luar_validasi_request(): void
    {
        $this->expectException(QueryException::class);
        $this->kegiatan()->forceFill(['anggaran' => -1])->save();
    }

    public function test_anggaran_nol_sah_dan_kosong_hanya_untuk_data_awal_lewat_jalur_perencanaan(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['anggaran' => 0]))->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $this->assertSame('0', $kegiatan->anggaran);

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['anggaran' => null, 'expected_state' => $this->editToken($kegiatan)]))
            ->assertSessionHasErrors('anggaran');
        $this->assertSame('0', $kegiatan->fresh()->anggaran);

        $tanpaAnggaran = array_diff_key($this->payload(), ['anggaran' => true]);
        $this->actingAs($this->perencanaan)->post('/kegiatan', $tanpaAnggaran)->assertSessionHasNoErrors();
        $this->assertNull(Kegiatan::where('created_by', $this->perencanaan->id)->sole()->anggaran);
        $this->actingAs($this->perencanaan)->post('/kegiatan', array_replace($tanpaAnggaran, ['tahun' => 2027]))->assertSessionHasErrors('anggaran');
    }

    public function test_ubah_data_rencana_mempertahankan_status_dan_data_pelaksanaan(): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->forceFill(['uraian_pelaksanaan' => 'Narasi histori'])->save();
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'nama' => 'Rencana diperbarui', 'expected_state' => $this->editToken($kegiatan),
            'status' => 'batal', 'uraian_pelaksanaan' => 'Payload tidak dipercaya',
        ]))->assertRedirect('/kegiatan?tahun=2026')->assertSessionHasNoErrors();

        $this->assertSame('Rencana diperbarui', $kegiatan->fresh()->nama);
        $this->assertSame('rencana', $kegiatan->fresh()->status);
        $this->assertSame('Narasi histori', $kegiatan->fresh()->uraian_pelaksanaan);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah')->sole();
        $this->assertSame('Rencana awal', $audit->nilai_lama['nama']);
        $this->assertSame('Rencana diperbarui', $audit->nilai_baru['nama']);
    }

    /** Data rencana hanya dapat diubah selama status `rencana` (Q69 §69.3). */
    #[DataProvider('statusSelainRencana')]
    public function test_data_rencana_status_selain_rencana_ditolak_422(string $status): void
    {
        $kegiatan = $this->kegiatan();
        $kegiatan->forceFill(['status' => $status])->save();

        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", [...$this->payload(), 'nama' => 'Ditolak', 'expected_state' => $kegiatan->fresh()->versionToken()])
            ->assertUnprocessable()->assertJsonValidationErrors(['status' => 'Data rencana terkunci karena status kegiatan sudah berubah.']);

        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertFalse(AuditLog::where('tindakan', 'kegiatan.ubah')->exists());
    }

    public static function statusSelainRencana(): array
    {
        return [['tidak_terlaksana'], ['terlaksana'], ['ditunda'], ['batal']];
    }

    public function test_request_lintas_unit_ditolak_dan_teraudit_tanpa_mutasi(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['unit_id' => $this->unitLain->id]))->assertForbidden();
        $kegiatan = $this->kegiatan($this->unitLain);
        // Mengirim unit A tidak boleh menyamarkan kepemilikan objek unit B.
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame($this->unitLain->id, $kegiatan->fresh()->unit_id);
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->count());
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->count());
    }

    public function test_pemindahan_unit_memerlukan_izin_unit_tujuan_juga(): void
    {
        $kegiatan = $this->kegiatan();
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), [
            'unit_id' => $this->unitLain->id, 'expected_state' => $this->editToken($kegiatan),
        ]))->assertForbidden();
        $this->assertSame($this->unit->id, $kegiatan->fresh()->unit_id);
    }

    public function test_deny_ubah_mematikan_capability_dan_menolak_request_bahkan_superadmin(): void
    {
        $aktor = $this->user('superadmin');
        $kegiatan = $this->kegiatan();
        $this->deny($aktor, 'kegiatan:update', $this->unit->id);
        $this->actingAs($aktor)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Index')->where('kegiatan.data.0.can.update', false));
        $this->actingAs($aktor)->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => 'token-tidak-dipakai'])->assertForbidden();
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        $this->assertNotEmpty(AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole()->dasar_izin);
    }

    public function test_unit_nonaktif_dan_periode_nonaktif_atau_asing_ditolak_sebagai_validasi(): void
    {
        $this->unitLain->update(['status' => 'nonaktif']);
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', array_replace($this->payload(), ['unit_id' => $this->unitLain->id]))->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->periode->update(['aktif' => false]);
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('periode_id');
        $this->actingAs($this->perencanaan)->postJson('/kegiatan', array_replace($this->payload(), ['periode_id' => (string) Str::uuid()]))->assertUnprocessable()->assertJsonValidationErrors('periode_id');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_daftar_hanya_mengirim_kegiatan_unit_berizin_dan_deny_tetap_menang(): void
    {
        $barisA = $this->kegiatan();
        $barisB = $this->kegiatan($this->unitLain);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Index')
            ->has('kegiatan.data', 1)->where('kegiatan.data.0.id', $barisA->id)->where('kegiatan.data.0.can.update', true)->where('can.create', true)->missing('kegiatan.data.0.anggaran'));
        $this->actingAs($this->user('pegawai'))->get('/kegiatan')->assertOk()->assertInertia(fn (Assert $page) => $page->has('kegiatan.data', 0));
        foreach (['perencanaan', 'pimpinan'] as $peran) {
            $this->actingAs($this->user($peran))->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->has('kegiatan.data', 2));
        }
        $this->actingAs($this->pegawai)->get("/kegiatan/{$barisB->id}/edit")->assertForbidden();
        $audit = AuditLog::where('tindakan', 'kegiatan.form_ditolak')->sole()->dasar_izin;
        $this->assertSame(['kegiatan:read', 'unit_tidak_berizin'], [$audit['permission'], $audit['alasan']]);

        $this->deny($this->pegawai, 'kegiatan:read', $this->unit->id);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->has('kegiatan.data', 0));
        $this->deny($this->pegawai, 'kegiatan:read', null);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertForbidden();
    }

    public function test_grant_tulis_tanpa_izin_baca_unit_ditolak_sejak_form_hingga_simpan(): void
    {
        $kegiatan = $this->kegiatan();
        $admin = $this->user('admin');
        foreach (['kegiatan:create', 'kegiatan:update'] as $code) {
            UserPermissionGrant::create(['user_id' => $admin->id, 'permission_id' => Permission::where('kode', $code)->value('id'), 'unit_id' => $this->unit->id, 'alasan' => 'Grant tanpa izin baca', 'diberikan_oleh' => $this->perencanaan->id]);
        }
        $token = $this->editToken($kegiatan);
        $this->actingAs($admin)->post('/kegiatan', $this->payload())->assertForbidden();
        $this->get("/kegiatan/{$kegiatan->id}/edit")->assertForbidden();
        $this->post("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $token])->assertForbidden();
        foreach (['kegiatan.form_ditolak', 'kegiatan.buat_ditolak', 'kegiatan.ubah_ditolak'] as $event) {
            $this->assertSame('kegiatan:read', AuditLog::where('tindakan', $event)->where('actor_id', $admin->id)->firstOrFail()->dasar_izin['permission']);
        }

        // Deny baca pada unit tulis menutup tombol, form, dan simpan pada unit itu.
        $this->deny($this->pegawai, 'kegiatan:read', $this->unit->id);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page->where('can.create', false)->has('creatableUnitOptions', 0));
        $this->post('/kegiatan', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_form_ubah_hanya_memberikan_unit_yang_boleh_diubah_dan_halaman_buat_tidak_tersedia(): void
    {
        $kegiatan = $this->kegiatan();
        UserPermissionGrant::create(['user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', 'kegiatan:update')->value('id'), 'unit_id' => $this->unitLain->id, 'alasan' => 'Izin update unit B', 'diberikan_oleh' => $this->perencanaan->id]);
        $this->actingAs($this->pegawai)->get("/kegiatan/{$kegiatan->id}/edit")->assertInertia(fn (Assert $page) => $page->component('Kegiatan/Form')
            ->where('kegiatan.id', $kegiatan->id)->has('unitOptions', 2)->where('unitOptions.1.id', $this->unitLain->id)->where('periodeOptions.0.id', $this->periode->id)->has('kegiatan.anggaran')->missing('tahunDefault'));
        $this->actingAs($this->user('pegawai'))->get("/kegiatan/{$kegiatan->id}/edit")->assertForbidden();
        $this->actingAs($this->pegawai)->get('/kegiatan/bukan-uuid/edit')->assertNotFound();
        // Buat kegiatan hanya lewat modal daftar dan POST /kegiatan.
        $this->get('/kegiatan/buat')->assertNotFound();
    }

    public function test_stale_edit_tidak_menimpa_data_terbaru(): void
    {
        $kegiatan = $this->kegiatan();
        $token = $this->editToken($kegiatan);
        $kegiatan->update(['nama' => 'Perubahan pengguna lain']);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", $this->payload() + ['expected_state' => $token])->assertUnprocessable()->assertJsonValidationErrors('expected_state');
        $this->assertSame('Perubahan pengguna lain', $kegiatan->fresh()->nama);
    }

    public function test_akun_yang_dinonaktifkan_sebelum_submit_ditolak_dan_diaudit_sekali(): void
    {
        $kegiatan = $this->kegiatan();
        $this->pegawai->update(['status' => 'nonaktif']);
        $this->actingAs($this->pegawai)->withHeader('X-Inertia', 'true')
            ->post('/kegiatan', $this->payload())->assertForbidden();
        $this->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['unit_id' => 'payload-rusak']))->assertForbidden();
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
        foreach (['kegiatan.buat_ditolak' => $this->unit->id, 'kegiatan.ubah_ditolak' => $kegiatan->id] as $event => $target) {
            $audit = AuditLog::where('tindakan', $event)->sole();
            $this->assertSame($target, $audit->objek_id);
            $this->assertSame($this->pegawai->id, $audit->actor_id);
            $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
        }
    }

    public function test_get_daftar_dan_form_yang_ditolak_mencatat_keputusan_resolver(): void
    {
        $kegiatan = $this->kegiatan();
        $this->deny($this->pegawai, 'kegiatan:read', null);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertForbidden();
        $this->get("/kegiatan/{$kegiatan->id}/edit")->assertForbidden();
        $this->assertSame('explicit_deny', AuditLog::where('tindakan', 'kegiatan.baca_ditolak')->sole()->dasar_izin['alasan']);
        $audit = AuditLog::where('tindakan', 'kegiatan.form_ditolak')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('kegiatan:read', $audit->dasar_izin['permission']);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);
    }

    public function test_action_simpan_mengaudit_penolakan_dengan_target_tepercaya_dan_keputusan_asli(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        $this->deny($this->pegawai, 'kegiatan:update', $this->unitLain->id);
        $decision = app(PermissionResolver::class)->resolve($this->pegawai, 'kegiatan:update', $this->unitLain->id);
        // Keputusan baru berbeda; audit harus mempertahankan keputusan yang menolak request.
        $this->pegawai->update(['status' => 'nonaktif']);
        $action = app(SimpanKegiatan::class);
        $action->catatPenolakan($this->pegawai, $kegiatan, $this->unit->id, 'Izin ditolak.', $decision);
        $audit = AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('kegiatan', $audit->objek_tipe);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);

        foreach ([strtoupper($this->unit->id), 'rusak', ['id' => $this->unit->id], (string) Str::uuid(), null] as $unitId) {
            $action->catatPenolakan($this->pegawai, null, $unitId, 'Akun tidak aktif.');
        }
        $audits = AuditLog::where('tindakan', 'kegiatan.buat_ditolak')->get();
        $this->assertCount(5, $audits);
        $this->assertSame(1, $audits->where('objek_tipe', 'unit')->where('objek_id', $this->unit->id)->count());
        $this->assertSame(4, $audits->where('objek_tipe', 'pengguna')->where('objek_id', $this->pegawai->id)->count());
        foreach ($audits as $audit) {
            $this->assertSame($this->pegawai->id, $audit->actor_id);
            $this->assertSame('inactive_user', $audit->dasar_izin['alasan']);
            $this->assertSame('kegiatan:create', $audit->dasar_izin['permission']);
            $this->assertNull($audit->nilai_baru);
        }
        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_action_daftar_mengaudit_penolakan_dengan_keputusan_asli_tanpa_mutasi(): void
    {
        $this->deny($this->pegawai, 'kegiatan:read', null);
        $decision = app(PermissionResolver::class)->resolve($this->pegawai, 'kegiatan:read');
        $this->pegawai->update(['status' => 'nonaktif']);
        app(DaftarKegiatan::class)->catatPenolakan($this->pegawai, $decision);
        $audit = AuditLog::where('tindakan', 'kegiatan.baca_ditolak')->sole();
        $this->assertSame($this->pegawai->id, $audit->actor_id);
        $this->assertSame($this->pegawai->id, $audit->objek_id);
        $this->assertSame('pengguna', $audit->objek_tipe);
        $this->assertSame('explicit_deny', $audit->dasar_izin['alasan']);
        $this->assertSame('kegiatan:read', $audit->dasar_izin['permission']);
        $this->assertNull($audit->nilai_baru);
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_get_form_ubah_tanpa_izin_diaudit_tanpa_mutasi(): void
    {
        $kegiatan = $this->kegiatan();
        $this->deny($this->pegawai, 'kegiatan:update', $this->unit->id);
        $this->actingAs($this->pegawai)->get("/kegiatan/{$kegiatan->id}/edit")->assertForbidden();
        $this->assertSame('kegiatan:update', AuditLog::where('tindakan', 'kegiatan.form_ditolak')->sole()->dasar_izin['permission']);
        $this->assertDatabaseCount('kegiatan', 1);
    }

    public function test_opsi_modal_buat_hanya_unit_tulis_dan_periode_nonaktif_tidak_ditawarkan(): void
    {
        UserPermissionGrant::create(['user_id' => $this->pegawai->id, 'permission_id' => Permission::where('kode', 'kegiatan:update')->value('id'), 'unit_id' => $this->unitLain->id, 'alasan' => 'Izin update unit B', 'diberikan_oleh' => $this->perencanaan->id]);
        $this->periode->update(['aktif' => false]);
        $this->actingAs($this->pegawai)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page
            ->has('creatableUnitOptions', 1)->where('creatableUnitOptions.0.id', $this->unit->id)->missing('editableUnitOptions')
            ->has('activePeriodeOptions', 0)->has('periodeOptions', 1));
    }

    public function test_simpan_kembali_ke_daftar_tahun_kegiatan_agar_hasil_tetap_terlihat(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['tahun' => 2027]))
            ->assertRedirect('/kegiatan?tahun=2027')->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $this->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['tahun' => 2025, 'expected_state' => $kegiatan->versionToken()]))
            ->assertRedirect('/kegiatan?tahun=2025')->assertSessionHasNoErrors();
    }

    public function test_kegiatan_unit_nonaktif_tidak_menawarkan_edit_meski_izin_dari_role(): void
    {
        $aktif = $this->kegiatan();
        $nonaktif = $this->kegiatan($this->unitLain);
        $this->unitLain->update(['status' => 'nonaktif']);
        // Writer mensyaratkan unit aktif; capability tidak boleh menawarkan aksi yang pasti ditolak.
        $this->actingAs($this->perencanaan)->get('/kegiatan')->assertInertia(fn (Assert $page) => $page
            ->has('kegiatan.data', 2)
            ->where('kegiatan.data', fn ($items) => collect($items)->firstWhere('id', $aktif->id)['can']['update'] === true
                && collect($items)->firstWhere('id', $nonaktif->id)['can']['update'] === false));
    }

    public function test_form_edit_kegiatan_unit_nonaktif_ditolak_dan_diaudit(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        $this->unitLain->update(['status' => 'nonaktif']);
        $this->actingAs($this->perencanaan)->get("/kegiatan/{$kegiatan->id}/edit")->assertForbidden();
        $audit = AuditLog::where('tindakan', 'kegiatan.form_ditolak')->sole();
        $this->assertSame($kegiatan->id, $audit->objek_id);
        $this->assertSame('inactive_unit', $audit->dasar_izin['alasan']);
        $this->assertSame('Rencana awal', $kegiatan->fresh()->nama);
    }

    public function test_nama_hingga_500_karakter_dan_tujuan_opsional(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', array_replace($this->payload(), ['nama' => str_repeat('n', 501)]))
            ->assertSessionHasErrors('nama');
        $nama = str_repeat('é', 500);
        $this->post('/kegiatan', array_replace($this->payload(), ['nama' => $nama, 'tujuan' => '']))->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $this->assertSame($nama, $kegiatan->nama);
        $this->assertNull($kegiatan->tujuan);
        $this->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['nama' => $nama, 'tujuan' => null, 'expected_state' => $kegiatan->versionToken()]))
            ->assertSessionHasNoErrors();
        $this->assertNull(AuditLog::where('tindakan', 'kegiatan.ubah')->sole()->nilai_baru['tujuan']);
    }

    public function test_gagal_audit_membatalkan_pembuatan(): void
    {
        $this->mock(AuditLogger::class)->shouldReceive('catat')->once()->andThrow(new RuntimeException('Audit gagal sintetis'));
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload());
            $this->fail('Mutasi seharusnya dibatalkan bila audit gagal.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit gagal sintetis', $exception->getMessage());
        }
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_capability_batch_antar_unit_memakai_keputusan_kanonis_dengan_query_tetap(): void
    {
        $this->deny($this->pegawai, 'kegiatan:update', $this->unit->id);
        $resolver = app(PermissionResolver::class);
        $scopes = [$this->unit->id, strtoupper($this->unit->id), $this->unitLain->id, null, 'bukan-uuid'];
        DB::enableQueryLog();
        DB::flushQueryLog();
        $batch = $resolver->decideManyForUnits($this->pegawai, ['kegiatan:create', 'kegiatan:update', 'kegiatan:read'], $scopes);
        $this->assertLessThanOrEqual(6, count(DB::getQueryLog()));
        DB::disableQueryLog();
        foreach ($scopes as $scope) {
            $this->assertSame($resolver->decideMany($this->pegawai, ['kegiatan:create', 'kegiatan:update', 'kegiatan:read'], $scope), $batch[$scope ?? '']);
        }
    }

    public function test_deny_dengan_payload_unit_rusak_tetap_diaudit(): void
    {
        $kegiatan = $this->kegiatan($this->unitLain);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['unit_id' => 'rusak']))->assertForbidden();
        $this->assertSame(1, AuditLog::where('tindakan', 'kegiatan.ubah_ditolak')->where('objek_id', $kegiatan->id)->count());
    }

    public function test_grant_unit_yang_dinonaktifkan_mendapat_error_master_spesifik(): void
    {
        $this->unit->update(['status' => 'nonaktif']);
        $this->actingAs($this->pegawai)->postJson('/kegiatan', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('unit_id');
        $this->assertDatabaseCount('kegiatan', 0);
    }

    public function test_filter_status_batal_dan_pagination_dikerjakan_server(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->kegiatan()->update(['status' => 'batal']);
        }
        $this->kegiatan();
        $this->actingAs($this->pegawai)->get('/kegiatan?status=batal&tahun=2026&page=2')->assertInertia(fn (Assert $page) => $page
            ->where('kegiatan.total', 21)->has('kegiatan.data', 1)->where('kegiatan.data.0.status', 'batal'));
    }

    public function test_konteks_kegiatan_yang_sudah_diklaim_tidak_dapat_dipindah_tetapi_nama_boleh_diubah(): void
    {
        $indikator = $this->targetIndicator($this->perencanaan);
        $indikator->update(['unit_id' => $this->unit->id]);
        $jadwal = JadwalTahunan::create(['renstra_id' => $indikator->sasaranStrategis->renstra_id, 'tahun' => 2026, 'penutupan' => '2026-12-31', 'status' => 'draft']);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'unit_id' => $this->unit->id, 'jadwal_tahunan_id' => $jadwal->id, 'penanggung_jawab_id' => $this->pegawai->id, 'tahun' => 2026, 'created_by' => $this->perencanaan->id]);
        $kegiatan = $this->kegiatan();
        KlaimKegiatan::create(['rencana_aksi_id' => $ra->id, 'kegiatan_id' => $kegiatan->id, 'arah_dampak' => 'menambah', 'sumber_klaim' => 'rencana_aksi', 'created_by' => $this->perencanaan->id, 'created_at' => now()]);
        $token = $this->editToken($kegiatan);
        $this->actingAs($this->pegawai)->postJson("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['tahun' => 2027, 'expected_state' => $token]))->assertUnprocessable()->assertJsonValidationErrors('tahun');
        $this->assertSame(2026, $kegiatan->fresh()->tahun);
        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", array_replace($this->payload(), ['nama' => 'Nama dikoreksi', 'unit_id' => strtoupper($this->unit->id), 'periode_id' => strtoupper($this->periode->id), 'expected_state' => $token]))->assertRedirect('/kegiatan?tahun=2026')->assertSessionHasNoErrors();
        $this->assertSame('Nama dikoreksi', $kegiatan->fresh()->nama);
    }

    public function test_tahun_dengan_jadwal_ditutup_menolak_buat_dan_ubah_serta_menyembunyikan_ubah(): void
    {
        // Kontrol positif: tanpa jadwal tahun itu, kegiatan tetap boleh dibuat.
        $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload())->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $jadwal = JadwalTahunan::create(['renstra_id' => $this->renstraUji()->id, 'tahun' => $kegiatan->tahun, 'penutupan' => ($kegiatan->tahun + 1).'-12-31', 'status' => 'aktif']);
        $ubah = fn (array $extra = []) => $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}",
            [...$this->payload(), 'nama' => 'Nama baru', 'expected_state' => $kegiatan->fresh()->versionToken(), ...$extra]);
        $ubah()->assertSessionHasNoErrors();

        $jadwal->update(['status' => 'ditutup', 'closed_at' => now()]);
        $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload())->assertSessionHasErrors('tahun');
        $ubah(['nama' => 'Ditolak'])->assertSessionHasErrors('tahun');
        // Memindahkan kegiatan dari tahun yang ditutup ke tahun terbuka juga ditolak.
        $ubah(['tahun' => $kegiatan->tahun + 5])->assertSessionHasErrors('tahun');
        $this->assertSame('Nama baru', $kegiatan->fresh()->nama);
        $this->assertSame($kegiatan->tahun, $kegiatan->fresh()->tahun);
        $this->assertDatabaseCount('kegiatan', 1);

        $this->actingAs($this->pegawai)->get('/kegiatan?tahun='.$kegiatan->tahun)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('kegiatan.data.0.can.update', false));
    }

    public function test_tanggal_penutupan_lewat_juga_mengunci_kegiatan(): void
    {
        JadwalTahunan::create(['renstra_id' => $this->renstraUji()->id, 'tahun' => 2026, 'penutupan' => today()->subDay()->toDateString(), 'status' => 'aktif']);

        $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload())->assertSessionHasErrors('tahun');
        // Tahun lain tanpa jadwal tetap bebas.
        $this->actingAs($this->pegawai)->post('/kegiatan', [...$this->payload(), 'tahun' => 2027])->assertSessionHasNoErrors();
    }

    public function test_simpan_mengunci_jadwal_tahun_sebelum_kegiatan_searah_pengajuan_pengukuran(): void
    {
        $this->actingAs($this->pegawai)->post('/kegiatan', $this->payload())->assertSessionHasNoErrors();
        $kegiatan = Kegiatan::sole();
        $kunci = [];
        DB::listen(function ($query) use (&$kunci): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'for update') || str_contains($sql, 'for share')) {
                $kunci[] = $sql;
            }
        });

        $this->actingAs($this->pegawai)->post("/kegiatan/{$kegiatan->id}", [...$this->payload(), 'nama' => 'Nama baru', 'expected_state' => $kegiatan->versionToken()])
            ->assertSessionHasNoErrors();

        // Pengajuan pengukuran mengunci jadwal lalu kegiatan; urutan terbalik di sini dapat deadlock.
        $jadwal = array_key_first(array_filter($kunci, fn (string $sql): bool => str_contains($sql, 'from "jadwal_tahunan"')));
        $baris = array_key_first(array_filter($kunci, fn (string $sql): bool => str_contains($sql, 'from "kegiatan"')));
        $this->assertNotNull($jadwal);
        $this->assertNotNull($baris);
        $this->assertLessThan($baris, $jadwal);
    }

    private function renstraUji(): Renstra
    {
        return Renstra::create(['kode' => 'R-'.Str::random(6), 'nama' => 'Renstra uji', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'created_by' => $this->perencanaan->id]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $user->roles()->attach(Role::where('kode', $role)->value('id'), ['id' => (string) Str::uuid(), 'diberikan_oleh' => $user->id, 'sumber_pemberian' => 'manual', 'created_at' => now()]);

        return $user;
    }

    private function payload(): array
    {
        return ['unit_id' => $this->unit->id, 'tahun' => 2026, 'periode_id' => $this->periode->id, 'nama' => 'Rencana baru', 'tujuan' => 'Mendukung kinerja unit', 'sasaran_peserta' => 0, 'keterangan_peserta' => null, 'lokasi' => null, 'tanggal_rencana' => null, 'anggaran' => 1500000];
    }

    private function editToken(Kegiatan $kegiatan): string
    {
        return $this->actingAs($this->pegawai)->get("/kegiatan/{$kegiatan->id}/edit")->viewData('page')['props']['kegiatan']['version_token'];
    }

    private function kegiatan(?Unit $unit = null): Kegiatan
    {
        return Kegiatan::create(array_replace($this->payload(), ['unit_id' => ($unit ?? $this->unit)->id, 'nama' => 'Rencana awal', 'created_by' => $this->perencanaan->id]));
    }

    private function deny(User $user, string $permission, ?string $unit): void
    {
        UserPermissionDeny::create(['user_id' => $user->id, 'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => $unit, 'alasan' => 'Pembatasan pengujian', 'ditetapkan_oleh' => $this->perencanaan->id]);
    }
}
