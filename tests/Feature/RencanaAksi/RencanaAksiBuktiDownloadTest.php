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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unduhan bukti dari layar reviu pengesahan memakai jalur unduh bukti rencana
 * aksi bersama; otorisasi baca dan konsistensi unit snapshot tetap berlaku.
 */
class RencanaAksiBuktiDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $perencana;

    private User $picUser;

    private Unit $unit;

    private JadwalTahunan $jadwal;

    private Periode $periode;

    private SasaranStrategis $sasaran;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(AccessCatalogSeeder::class);
        $this->perencana = $this->userWithRole('perencanaan');
        $this->picUser = $this->userWithRole('pegawai');
        $superadmin = $this->userWithRole('superadmin');
        $this->unit = Unit::create(['nama' => 'Unit Unduh Bukti RA', 'created_by' => $superadmin->id]);
        $renstra = Renstra::create(['kode' => 'R-BUKTI', 'nama' => 'Renstra Bukti', 'tahun_mulai' => 2025, 'tahun_selesai' => 2029, 'is_aktif' => true, 'created_by' => $superadmin->id]);
        $this->sasaran = SasaranStrategis::create(['renstra_id' => $renstra->id, 'kode' => 'S-BUKTI', 'deskripsi' => 'Sasaran Bukti']);
        $pk = RenstraPk::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'nomor_pk' => 'PK-BUKTI', 'tanggal_pk' => '2026-01-01', 'created_by' => $superadmin->id]);
        $this->periode = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        $this->jadwal = JadwalTahunan::create(['renstra_id' => $renstra->id, 'tahun' => 2026, 'renstra_pk_id' => $pk->id, 'penutupan' => '2026-12-31', 'status' => 'aktif', 'activated_at' => now()]);
    }

    /**
     * RA diverifikasi dengan satu bukti file yang masih berlaku dan dirujuk
     * snapshot versi pengajuan (Q36: bukti tidak berubah sejak diajukan).
     *
     * @return array{0: RencanaAksi, 1: BuktiDukung}
     */
    private function buatRencanaAksiDenganBuktiFile(): array
    {
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->sasaran->id, 'unit_id' => $this->unit->id,
            'kode' => 'I-BUKTI', 'nama' => 'Indikator Bukti', 'satuan' => 'poin',
            'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencana->id, 'created_by_role' => 'perencanaan']);
        $snapshot = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->periode->id,
            'unit_id' => $this->unit->id, 'nama' => 'Indikator Bukti', 'definisi' => 'Definisi operasional beku.',
            'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->unit->id,
            'jadwal_tahunan_id' => $this->jadwal->id, 'penanggung_jawab_id' => $this->picUser->id,
            'created_by' => $this->perencana->id, 'status_alur' => 'diverifikasi']);
        Storage::disk('local')->put('rencana-aksi/beku.pdf', 'Isi bukti beku');
        $bukti = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id, 'mode' => 'file',
            'nama_asli' => 'beku.pdf', 'path' => 'rencana-aksi/beku.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 14,
            'uploaded_by' => $this->picUser->id, 'created_at' => now()]);
        RencanaAksiVersi::create(['rencana_aksi_id' => $ra->id, 'jadwal_snapshot_id' => $snapshot->id, 'nomor' => 1,
            'diajukan_by' => $this->picUser->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic',
            'dasar_izin_pengajuan' => ['jalur' => 'pic', 'unit_id' => $this->unit->id],
            'snapshot' => ['uraian' => 'Versi pengajuan beku.',
                'indikator' => ['kode' => $indikator->kode, 'nama' => $indikator->nama],
                'unit_kerja' => ['id' => $this->unit->id, 'nama' => $this->unit->nama],
                'pic' => ['id' => $this->picUser->id, 'nama' => $this->picUser->nama],
                'target_periode' => [['periode_id' => $this->periode->id, 'periode_nama' => $this->periode->nama, 'periode_urutan' => $this->periode->urutan,
                    'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]],
                'bukti_dukungs' => [$bukti->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan', 'isi_teks', 'path'])]]]);

        return [$ra, $bukti];
    }

    private function urlUnduh(RencanaAksi $ra, BuktiDukung $bukti): string
    {
        return route('rencana-aksi.bukti.download', ['rencanaAksi' => $ra->id, 'bukti' => $bukti->id]);
    }

    public function test_layar_reviu_menautkan_unduhan_ke_jalur_bukti_rencana_aksi(): void
    {
        [$ra, $bukti] = $this->buatRencanaAksiDenganBuktiFile();
        $url = $this->urlUnduh($ra, $bukti);

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id.'/reviu')->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.evidence', true)
            ->where('rencanaAksi.bukti_dukungs.0.download_url', $url)
            ->missing('rencanaAksi.bukti_dukungs.0.path'));
        $this->actingAs($this->perencana)->get($url)->assertOk()->assertStreamedContent('Isi bukti beku');
    }

    public function test_unduh_bukti_menolak_deny_berkas_dan_tanpa_read(): void
    {
        [$ra, $bukti] = $this->buatRencanaAksiDenganBuktiFile();
        $url = $this->urlUnduh($ra, $bukti);

        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $this->perencana->id,
            'permission_id' => Permission::where('kode', 'berkas:read')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);
        $this->actingAs($this->perencana)->get($url)->assertForbidden();

        $pegawai = $this->userWithRole('pegawai');
        DB::table('user_permission_denied')->insert(['id' => Str::uuid(), 'user_id' => $pegawai->id,
            'permission_id' => Permission::where('kode', 'rencana_aksi:read')->value('id'), 'unit_id' => null,
            'alasan' => 'Pencabutan pengujian', 'ditetapkan_oleh' => $this->perencana->id, 'created_at' => now()]);
        $this->actingAs($pegawai)->get($url)->assertForbidden();
    }

    public function test_unduh_bukti_ditolak_saat_unit_header_tidak_konsisten(): void
    {
        [$ra, $bukti] = $this->buatRencanaAksiDenganBuktiFile();
        $unitLain = Unit::create(['nama' => 'Unit Snapshot Beda', 'created_by' => $this->perencana->id]);
        DB::table('rencana_aksi')->where('id', $ra->id)->update(['unit_id' => $unitLain->id]);

        $this->actingAs($this->perencana)->get($this->urlUnduh($ra, $bukti))->assertForbidden();
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }
}
