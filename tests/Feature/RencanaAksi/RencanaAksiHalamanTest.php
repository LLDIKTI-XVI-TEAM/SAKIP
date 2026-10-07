<?php

namespace Tests\Feature\RencanaAksi;

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

    public function test_index_menyembunyikan_unit_terdeny_dan_menolak_tanpa_izin_sahkan(): void
    {
        $unitLain = Unit::create(['nama' => 'Unit Lain Halaman', 'created_by' => $this->perencana->id]);
        $tersembunyi = $this->buatRencanaAksi('diverifikasi', $unitLain, 'pic');
        $terlihat = $this->buatRencanaAksi('diverifikasi', null, 'pic');
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:sahkan')->value('id'),
            'unit_id' => $unitLain->id, 'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);

        $this->actingAs($this->perencana)->get('/rencana-aksi')->assertOk()->assertInertia(fn ($page) => $page
            ->where('pagination.total', 1)
            ->where('rencanaAksis.0.id', $terlihat->id)
            ->missing('rencanaAksis.1'));
        $this->assertNotContains($tersembunyi->id, [$terlihat->id]);

        $pegawai = $this->userWithRole('pegawai');
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

        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get('/rencana-aksi/'.$ra->id)->assertForbidden();

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
