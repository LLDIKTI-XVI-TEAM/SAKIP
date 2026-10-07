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
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ISS-05.05 halaman: antrean index + detail show untuk pengesahan rencana aksi.
 * Fixture meniru SahkanRencanaAksiTest; tidak menduplikasi 8 test sahkan.
 */
class RencanaAksiHalamanTest extends TestCase
{
    use RefreshDatabase;

    private User $perencana;

    private User $picUser;

    private Unit $unit;

    private JadwalTahunan $jadwal;

    private Periode $periode;

    private Renstra $renstra;

    private SasaranStrategis $sasaran;

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 3, 15)->setTime(9, 0));
        $this->seed(AccessCatalogSeeder::class);
        $this->perencana = $this->userWithRole('perencanaan');
        $this->picUser = $this->userWithRole('pegawai');
        $superadmin = $this->userWithRole('superadmin');
        $this->unit = Unit::create(['nama' => 'Unit Pengujian RA Halaman', 'created_by' => $superadmin->id]);
        $this->renstra = Renstra::create(['kode' => 'R-RAH', 'nama' => 'Renstra RA Halaman', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_aktif' => true, 'created_by' => $superadmin->id]);
        $this->sasaran = SasaranStrategis::create(['renstra_id' => $this->renstra->id, 'kode' => 'S-RAH', 'deskripsi' => 'Sasaran RA Halaman']);
        $pk = RenstraPk::create(['renstra_id' => $this->renstra->id, 'tahun' => 2026, 'nomor_pk' => 'PK-RAH', 'tanggal_pk' => '2026-01-01', 'created_by' => $superadmin->id]);
        $this->periode = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->jadwal = JadwalTahunan::create(['renstra_id' => $this->renstra->id, 'tahun' => 2026, 'renstra_pk_id' => $pk->id, 'penutupan' => '2026-12-31', 'status' => 'aktif', 'activated_at' => now()]);
        $this->grant($this->picUser, 'rencana_aksi:ajukan');
    }

    private function buatRencanaAksi(string $status, ?Unit $unit = null, ?string $jalur = null, ?User $diajukanBy = null): RencanaAksi
    {
        $this->counter++;
        $unit ??= $this->unit;
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->sasaran->id, 'unit_id' => $unit->id, 'kode' => 'I-RAH-'.$this->counter, 'nama' => 'Indikator RA Halaman '.$this->counter,
            'satuan' => 'poin', 'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencana->id, 'created_by_role' => 'perencanaan']);
        $snapshot = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->periode->id, 'unit_id' => $unit->id,
            'nama' => 'Indikator RA Halaman '.$this->counter, 'definisi' => 'Definisi operasional beku.', 'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2,
            'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $unit->id, 'jadwal_tahunan_id' => $this->jadwal->id,
            'jadwal_snapshot_id' => $snapshot->id, 'penanggung_jawab_id' => $this->picUser->id, 'created_by' => $this->perencana->id, 'status_alur' => $status]);
        if ($jalur !== null) {
            RencanaAksiVersi::create(['rencana_aksi_id' => $ra->id, 'jadwal_snapshot_id' => $snapshot->id, 'nomor' => 1, 'diajukan_by' => ($diajukanBy ?? $this->picUser)->id,
                'diajukan_at' => now(), 'jalur_pengajuan' => $jalur, 'dasar_izin_pengajuan' => ['jalur' => $jalur, 'unit_id' => $unit->id],
                'snapshot' => ['uraian' => 'Versi pengajuan beku.', 'target_periode' => [['periode_id' => $this->periode->id, 'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]]]]);
        }

        return $ra;
    }

    public function test_index_hanya_menampilkan_diajukan_dan_diverifikasi(): void
    {
        $diajukan = $this->buatRencanaAksi('diajukan', null, 'pic');
        $diverifikasi = $this->buatRencanaAksi('diverifikasi', null, 'pic');
        $this->buatRencanaAksi('draft');
        $this->buatRencanaAksi('dikembalikan');
        $this->buatRencanaAksi('disahkan');

        $this->actingAs($this->perencana)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->component('RencanaAksi/Index')
            ->where('pagination.total', 2)
            ->has('rencanaAksis', 2)
            ->where('rencanaAksis', fn ($list) => collect($list)->pluck('id')->sort()->values()->all()
                === collect([$diajukan->id, $diverifikasi->id])->sort()->values()->all()));
    }

    public function test_index_menyembunyikan_unit_terdeny_read_dan_mengizinkan_read_only_tanpa_sahkan(): void
    {
        $unitLain = Unit::create(['nama' => 'Unit Lain Halaman', 'created_by' => $this->perencana->id]);
        $tersembunyi = $this->buatRencanaAksi('diverifikasi', $unitLain, 'pic');
        $terlihat = $this->buatRencanaAksi('diverifikasi', null, 'pic');
        // Deny read menyembunyikan unit; deny sahkan tidak menyembunyikan antrean lihat.
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'),
            'unit_id' => $unitLain->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);

        $this->actingAs($this->perencana)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)
            ->where('rencanaAksis.0.id', $terlihat->id)
            ->missing('rencanaAksis.1'));
        $this->assertNotContains($tersembunyi->id, [$terlihat->id]);

        // Pegawai read-only (tanpa sahkan) tetap boleh lihat antrean.
        $pegawai = $this->userWithRole('pegawai');
        $this->actingAs($pegawai)->get('/rencana-aksi')->assertOk();

        // Tanpa read global (deny global) tetap 403.
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $pegawai->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'),
            'unit_id' => null, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);
        $this->actingAs($pegawai)->get('/rencana-aksi')->assertForbidden();
    }

    public function test_index_pagination_dua_halaman_untuk_21_pengajuan(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->buatRencanaAksi($i % 2 === 0 ? 'diajukan' : 'diverifikasi', null, 'pic');
        }

        $this->actingAs($this->perencana)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 21)->where('pagination.last_page', 2)->where('pagination.current_page', 1));
        $this->actingAs($this->perencana)->get('/rencana-aksi?page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.current_page', 2)->where('pagination.total', 21));
    }

    public function test_show_menolak_tanpa_read_dan_unit_terdeny_serta_uuid_asing_404(): void
    {
        $ra = $this->buatRencanaAksi('diverifikasi', null, 'pic');

        // Admin read-only boleh lihat detail, tetapi can.ratify false.
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('RencanaAksi/Show')->where('rencanaAksi.can.ratify', false)->where('rencanaAksi.can.evidence', false));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);
        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id)->assertForbidden();

        $this->actingAs($this->userWithRole('perencanaan'))->get('/rencana-aksi/'.(string) Str::uuid())->assertNotFound();
    }

    public function test_show_can_ratify_false_untuk_f1_dan_true_untuk_perencana(): void
    {
        $reviewer = $this->userWithRole('perencanaan');
        $raF1 = $this->buatRencanaAksi('diverifikasi', null, 'pic', $reviewer);
        $raSah = $this->buatRencanaAksi('diverifikasi', null, 'pic', $this->picUser);

        $this->actingAs($reviewer)->get('/rencana-aksi/'.$raF1->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('RencanaAksi/Show')->where('rencanaAksi.id', $raF1->id)->where('rencanaAksi.can.ratify', false));

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$raSah->id)->assertOk()->assertInertia(fn ($page) => $page
            ->component('RencanaAksi/Show')->where('rencanaAksi.can.ratify', true)->where('rencanaAksi.indikator.kode', $raSah->fresh()->indikator->kode));
    }

    public function test_read_only_admin_pimpinan_pegawai_lihat_tetapi_sahkan_403(): void
    {
        $ra = $this->buatRencanaAksi('diverifikasi', null, 'pic', $this->picUser);

        foreach (['admin', 'pimpinan', 'pegawai'] as $kode) {
            $user = $this->userWithRole($kode);
            $this->actingAs($user)->get('/rencana-aksi')->assertOk();
            $this->actingAs($user)->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
                ->component('RencanaAksi/Show')->where('rencanaAksi.can.ratify', false));
            // Tombol tersembunyi mengikuti can.ratify false di Show.tsx; POST tetap ditolak.
            $this->actingAs($user)->post('/rencana-aksi/'.$ra->id.'/sahkan', ['versi' => 1])->assertForbidden();
        }
    }

    public function test_deny_berkas_read_menyembunyikan_bukti_tetapi_count_tetap(): void
    {
        // RA tanpa versi dulu agar snapshot beku bisa di-INSERT dengan ID bukti yang sudah ada.
        $ra = $this->buatRencanaAksi('diverifikasi');
        $buktiId = (string) Str::uuid();
        $buktiSnapshot = [['id' => $buktiId, 'jenis_berkas_id' => null, 'menggantikan_id' => null, 'alasan_koreksi' => null,
            'mode' => 'tautan', 'nama_asli' => 'Dokumen Rahasia', 'mime' => null, 'ukuran_bytes' => null, 'tautan' => 'https://rahasia.internal/dokumen', 'isi_teks' => null]];
        RencanaAksiVersi::create(['rencana_aksi_id' => $ra->id, 'jadwal_snapshot_id' => $ra->jadwal_snapshot_id, 'nomor' => 1,
            'diajukan_by' => $this->picUser->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic',
            'dasar_izin_pengajuan' => ['jalur' => 'pic', 'unit_id' => $this->unit->id],
            'snapshot' => ['uraian' => 'Versi pengajuan beku.',
                'target_periode' => [['periode_id' => $this->periode->id, 'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]],
                'bukti_dukungs' => $buktiSnapshot]]);

        // Perencana normal (punya berkas:read) melihat bukti.
        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.evidence', true)->has('rencanaAksi.bukti_dukungs', 1)
            ->where('rencanaAksi.bukti_dukungs.0.tautan', 'https://rahasia.internal/dokumen'));

        // Deny berkas:read unit target menyembunyikan isi bukti.
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'berkas:read')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);
        // Cabut fallback kelola agar deny benar-benar menutup akses bukti.
        DB::table('role_permissions')->where('role_id', Role::where('kode', 'perencanaan')->value('id'))
            ->whereIn('permission_id', Permission::whereIn('kode', ['rencana_aksi:update', 'rencana_aksi:ajukan'])->pluck('id'))->delete();

        $this->actingAs($this->perencana->fresh())->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.evidence', false)->has('rencanaAksi.bukti_dukungs', 0)
            ->where('rencanaAksi.bukti_count', 1)->missing('rencanaAksi.bukti_dukungs.0.tautan'));
    }

    public function test_snapshot_tanpa_bukti_dukungs_tidak_fallback_live(): void
    {
        $ra = $this->buatRencanaAksi('diverifikasi', null, 'pic', $this->picUser);
        // Snapshot fixture memang tanpa key bukti_dukungs; buat bukti live yang tidak boleh bocor ke beku.
        $live = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti live yang tidak dibekukan.', 'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.bukti_count', 0)->has('rencanaAksi.bukti_dukungs', 0));

        $this->assertNotNull($live->id);
        // Target periode tanpa key juga kosong, bukan live (pola sama, sudah benar).
        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id)->assertOk();
    }

    public function test_delete_evidence_baru_pasca_buka_kembali_bisa_dihapus(): void
    {
        $ra = $this->buatRencanaAksi('diverifikasi');
        $lama = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti resmi beku.', 'uploaded_by' => $this->picUser->id, 'created_at' => now()]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $ra->id, 'jadwal_snapshot_id' => $ra->jadwal_snapshot_id, 'nomor' => 1,
            'diajukan_by' => $this->picUser->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic',
            'dasar_izin_pengajuan' => ['jalur' => 'pic', 'unit_id' => $this->unit->id],
            'snapshot' => ['uraian' => 'Versi pengajuan beku.',
                'target_periode' => [['periode_id' => $this->periode->id, 'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]],
                'bukti_dukungs' => [[
                    'id' => $lama->id, 'jenis_berkas_id' => null, 'menggantikan_id' => null, 'alasan_koreksi' => null,
                    'mode' => 'teks', 'nama_asli' => null, 'mime' => null, 'ukuran_bytes' => null, 'tautan' => null, 'isi_teks' => 'Bukti resmi beku.']]]]);

        // Sahkan agar ratifiedVersion terbentuk.
        $this->actingAs($this->perencana)->post('/rencana-aksi/'.$ra->id.'/sahkan', ['versi' => 1])->assertSessionHasNoErrors();
        $ra = $ra->fresh();
        $this->assertSame('disahkan', $ra->status_alur);
        $this->assertTrue(Gate::forUser($this->perencana)->inspect('deleteEvidence', [$ra, $lama])->denied());

        // Buka-kembali resmi: status dikembalikan tetapi ratifiedVersion tetap ada.
        $ra->update(['status_alur' => 'dikembalikan']);
        $ra = $ra->fresh();
        $this->assertNotNull($ra->ratifiedVersion);

        $baru = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id,
            'mode' => 'teks', 'isi_teks' => 'Bukti baru pasca-buka-kembali.', 'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        // Bukti lama (ID di snapshot resmi) tetap beku; bukti baru bisa dihapus.
        $this->assertTrue(Gate::forUser($this->perencana)->inspect('deleteEvidence', [$ra->fresh(), $lama])->denied());
        $this->assertTrue(Gate::forUser($this->perencana)->inspect('deleteEvidence', [$ra->fresh(), $baru])->allowed());
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
