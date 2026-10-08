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
 * Bukti dukung Rencana Aksi: unduh file privat ber-otorisasi dari snapshot beku,
 * tanpa fallback relasi live untuk status beku (temuan review Codex 2026-10-08).
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

    private int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
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

    private function buatRencanaAksi(string $status, ?array $snapshotBukti, bool $denganVersi = true): RencanaAksi
    {
        $this->counter++;
        $indikator = IndikatorKinerja::create(['sasaran_strategis_id' => $this->sasaran->id, 'unit_id' => $this->unit->id,
            'kode' => 'I-BUKTI-'.$this->counter, 'nama' => 'Indikator Bukti '.$this->counter, 'satuan' => 'poin',
            'tipe_perhitungan' => 'manual', 'status' => 'aktif', 'tahun_mulai_berlaku' => 2025,
            'created_by' => $this->perencana->id, 'created_by_role' => 'perencanaan']);
        $snapshot = JadwalSnapshot::create(['jadwal_id' => $this->jadwal->id, 'indikator_id' => $indikator->id, 'periode_mulai_id' => $this->periode->id,
            'unit_id' => $this->unit->id, 'nama' => 'Indikator Bukti '.$this->counter, 'definisi' => 'Definisi operasional beku.',
            'satuan' => 'poin', 'presisi' => 2, 'desimal_tampilan' => 2, 'arah' => 'naik_baik', 'tipe_perhitungan' => 'manual', 'target' => 70]);
        $ra = RencanaAksi::create(['indikator_id' => $indikator->id, 'tahun' => 2026, 'unit_id' => $this->unit->id,
            'jadwal_tahunan_id' => $this->jadwal->id, 'jadwal_snapshot_id' => $snapshot->id, 'penanggung_jawab_id' => $this->picUser->id,
            'created_by' => $this->perencana->id, 'status_alur' => $status]);
        if ($denganVersi) {
            $payload = ['uraian' => 'Versi pengajuan beku.',
                'indikator' => ['kode' => $indikator->kode, 'nama' => $indikator->nama],
                'unit_kerja' => ['id' => $this->unit->id, 'nama' => $this->unit->nama],
                'pic' => ['id' => $this->picUser->id, 'nama' => $this->picUser->nama],
                'target_periode' => [['periode_id' => $this->periode->id, 'periode_nama' => $this->periode->nama, 'periode_urutan' => $this->periode->urutan,
                    'nilai' => 70, 'status_perhitungan' => 'terhitung', 'komponen' => []]]];
            if ($snapshotBukti !== null) {
                $payload['bukti_dukungs'] = $snapshotBukti;
            }
            RencanaAksiVersi::create(['rencana_aksi_id' => $ra->id, 'jadwal_snapshot_id' => $snapshot->id, 'nomor' => 1,
                'diajukan_by' => $this->picUser->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'pic',
                'dasar_izin_pengajuan' => ['jalur' => 'pic', 'unit_id' => $this->unit->id], 'snapshot' => $payload]);
        }

        return $ra;
    }

    /** @return array<string, mixed> */
    private function buktiFileBeku(string $id, string $path): array
    {
        return ['id' => $id, 'jenis_berkas_id' => null, 'menggantikan_id' => null, 'alasan_koreksi' => null,
            'mode' => 'file', 'nama_asli' => basename($path), 'mime' => 'application/pdf', 'ukuran_bytes' => 12,
            'tautan' => null, 'isi_teks' => null, 'path' => $path];
    }

    public function test_unduh_bukti_file_beku_memakai_metadata_snapshot(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rencana-aksi/beku.pdf', 'Isi bukti beku');
        $buktiId = (string) Str::uuid();
        $ra = $this->buatRencanaAksi('diverifikasi', [$this->buktiFileBeku($buktiId, 'rencana-aksi/beku.pdf')]);
        // Path relasi live berubah setelah submit: unduhan tetap memakai metadata beku.
        BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id, 'mode' => 'file',
            'nama_asli' => 'beku.pdf', 'path' => 'rencana-aksi/berubah.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 12,
            'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        $url = '/rencana-aksi/'.$ra->id.'/bukti/'.$buktiId;
        $this->actingAs($this->perencana)->get($url)->assertOk()->assertStreamedContent('Isi bukti beku');
        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id.'/bukti/'.(string) Str::uuid())->assertNotFound();

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('rencanaAksi.can.evidence', true)
            ->where('rencanaAksi.bukti_dukungs.0.download_url', url($url))
            ->missing('rencanaAksi.bukti_dukungs.0.path'));
    }

    public function test_unduh_bukti_menolak_deny_berkas_dan_tanpa_read(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rencana-aksi/beku.pdf', 'Isi bukti beku');
        $buktiId = (string) Str::uuid();
        $ra = $this->buatRencanaAksi('diverifikasi', [$this->buktiFileBeku($buktiId, 'rencana-aksi/beku.pdf')]);
        $url = '/rencana-aksi/'.$ra->id.'/bukti/'.$buktiId;

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

    public function test_unduh_bukti_draft_memakai_relasi_live(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rencana-aksi/draf.pdf', 'Isi draf');
        $ra = $this->buatRencanaAksi('draft', null, denganVersi: false);
        $bukti = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id, 'mode' => 'file',
            'nama_asli' => 'draf.pdf', 'path' => 'rencana-aksi/draf.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 8,
            'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id.'/bukti/'.$bukti->id)->assertOk()->assertStreamedContent('Isi draf');
    }

    public function test_unduh_bukti_beku_tidak_fallback_ke_relasi_live(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rencana-aksi/live.pdf', 'Bukti live');
        $ra = $this->buatRencanaAksi('diverifikasi', null);
        $live = BuktiDukung::create(['berkasable_type' => 'rencana_aksi', 'berkasable_id' => $ra->id, 'mode' => 'file',
            'nama_asli' => 'live.pdf', 'path' => 'rencana-aksi/live.pdf', 'mime' => 'application/pdf', 'ukuran_bytes' => 9,
            'uploaded_by' => $this->picUser->id, 'created_at' => now()]);

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id.'/bukti/'.$live->id)->assertNotFound();
    }

    public function test_unduh_bukti_ditolak_saat_unit_header_tidak_konsisten(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('rencana-aksi/beku.pdf', 'Isi bukti beku');
        $buktiId = (string) Str::uuid();
        $ra = $this->buatRencanaAksi('diverifikasi', [$this->buktiFileBeku($buktiId, 'rencana-aksi/beku.pdf')]);
        $unitLain = Unit::create(['nama' => 'Unit Snapshot Beda', 'created_by' => $this->perencana->id]);
        DB::table('rencana_aksi')->where('id', $ra->id)->update(['unit_id' => $unitLain->id]);

        $this->actingAs($this->perencana)->get('/rencana-aksi/'.$ra->id.'/bukti/'.$buktiId)->assertForbidden();
    }

    private function userWithRole(string $kode): User
    {
        $user = User::factory()->create(['status' => 'aktif']);
        $role = Role::where('kode', $kode)->firstOrFail();
        $user->roles()->attach($role->id, ['id' => (string) Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);

        return $user;
    }
}
