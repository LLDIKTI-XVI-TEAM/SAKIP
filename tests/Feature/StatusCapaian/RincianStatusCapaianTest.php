<?php

namespace Tests\Feature\StatusCapaian;

use App\Actions\StatusCapaian\TetapkanStatusCapaian;
use App\Models\Permission;
use App\Models\StatusCapaian;
use App\Models\User;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Kontrak halaman rincian status capaian (`GET /verifikasi/status-capaian/{pengukuran}`): akses sama persis dengan
 * daftar. Pengukuran yang tidak tampil di salah satu dari dua daftar bagi pengguna itu menghasilkan 404, dan baris
 * yang dikirim sama dengan baris daftar. Setiap penolakan disertai kontrol positif pada fixture yang sama.
 */
class RincianStatusCapaianTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private const KOMPONEN = 'Verifikasi/StatusCapaianRincian';

    private function buka(string $pengukuranId, ?User $sebagai = null): TestResponse
    {
        return $this->actingAs($sebagai ?? $this->actor)->get("/verifikasi/status-capaian/{$pengukuranId}");
    }

    /** @return array<string, mixed> */
    private function propsRincian(string $pengukuranId, ?User $sebagai = null): array
    {
        $page = $this->buka($pengukuranId, $sebagai)->assertOk()->viewData('page');
        $this->assertSame(self::KOMPONEN, $page['component']);

        return $page['props'];
    }

    /** @return array<string, mixed> */
    private function propsDaftar(?User $sebagai = null): array
    {
        return $this->actingAs($sebagai ?? $this->actor)->get('/verifikasi/status-capaian')->assertOk()->viewData('page')['props'];
    }

    private function tetapkan(string $pengukuranId, string $versiId, string $status): StatusCapaian
    {
        return app(TetapkanStatusCapaian::class)->handle($this->actor, $pengukuranId, [
            'pengukuran_versi_id' => $versiId,
            'status' => $status,
            'versi' => (int) DB::table('pengukuran_kinerjas')->where('id', $pengukuranId)->value('versi'),
        ]);
    }

    private function tolak(User $user, string $unitId): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $user->id,
            'permission_id' => Permission::where('kode', PermissionCodes::STATUS_CAPAIAN_UPDATE)->value('id'),
            'unit_id' => $unitId, 'alasan' => 'Deny pengujian',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
    }

    public function test_rincian_kandidat_memuat_baris_yang_sama_dengan_daftar(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80', 'target' => '100', 'target_pk' => '90']);
        // Pengukuran kedua memastikan yang dikirim adalah baris yang diminta, bukan baris pertama yang ditemukan.
        $lain = $this->headerUntuk($this->indikatorBaru('I-LAIN'));
        $versiLain = $this->sahkan($lain, ['nilai' => '10']);

        $props = $this->propsRincian($this->pengukuran->id);

        $this->assertSame(['tetapkan' => true], $props['can']);
        $this->assertSame($this->pengukuran->id, $props['pengukuran']['id']);
        $this->assertSame($versi->id, $props['pengukuran']['versi_pengukuran']['id']);
        $this->assertSame('I-UJI', $props['pengukuran']['indikator']['kode']);
        $this->assertArrayNotHasKey('status_berlaku', $props['pengukuran']);
        $this->assertSame([], $props['pengukuran']['riwayat']);
        $this->assertSame(['tetapkan' => true, 'revisi' => false], $props['pengukuran']['can']);

        $daftar = collect($this->propsDaftar()['kandidat'])->keyBy('id');
        $this->assertSame($daftar[$this->pengukuran->id], $props['pengukuran'], 'Baris rincian harus sama dengan baris daftar kandidat.');

        $propsLain = $this->propsRincian($lain->id);
        $this->assertSame($versiLain->id, $propsLain['pengukuran']['versi_pengukuran']['id']);
        $this->assertSame($daftar[$lain->id], $propsLain['pengukuran']);
    }

    public function test_rincian_sudah_ditetapkan_memuat_status_berlaku_dan_riwayat(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $pertama = $this->tetapkan($this->pengukuran->id, $versi->id, 'tercapai');
        $this->travel(1)->minutes();
        $revisi = $this->tetapkan($this->pengukuran->id, $versi->id, 'belum_tercapai');

        $baris = $this->propsRincian($this->pengukuran->id)['pengukuran'];

        $this->assertSame($revisi->id, $baris['status_berlaku']['id']);
        $this->assertSame('belum_tercapai', $baris['status_berlaku']['status']);
        $this->assertSame($this->actor->nama, $baris['status_berlaku']['penetap']);
        $this->assertSame($versi->id, $baris['status_berlaku']['versi_id']);
        $this->assertSame([$revisi->id, $pertama->id], array_column($baris['riwayat'], 'id'), 'Riwayat diurut dari yang terbaru.');
        $this->assertSame(['tetapkan' => false, 'revisi' => true], $baris['can']);

        $daftar = collect($this->propsDaftar()['sudah_ditetapkan'])->keyBy('id');
        $this->assertSame($daftar[$this->pengukuran->id], $baris, 'Baris rincian harus sama dengan baris daftar yang sudah ditetapkan.');
    }

    /** Dialog di halaman rincian memakai endpoint simpan yang sama; hasilnya kembali ke rincian yang sudah berstatus. */
    public function test_penetapan_dari_halaman_rincian_kembali_ke_rincian_dengan_status_berlaku(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');
        $rincian = "/verifikasi/status-capaian/{$this->pengukuran->id}";
        $token = $this->propsRincian($this->pengukuran->id, $perencanaan)['pengukuran']['versi'];

        $this->actingAs($perencanaan)->from($rincian)
            ->post("/verifikasi/{$this->pengukuran->id}/status-capaian", [
                'pengukuran_versi_id' => $versi->id, 'status' => 'tercapai', 'versi' => $token,
            ])
            ->assertRedirect($rincian)
            ->assertSessionHasNoErrors();

        $baris = $this->propsRincian($this->pengukuran->id, $perencanaan)['pengukuran'];
        $this->assertSame('tercapai', $baris['status_berlaku']['status']);
        $this->assertSame($perencanaan->nama, $baris['status_berlaku']['penetap']);
        $this->assertSame(['tetapkan' => false, 'revisi' => true], $baris['can']);
        $this->assertSame($token + 1, $baris['versi'], 'Token konkurensi naik setelah penetapan.');

        // Token lama dari tampilan rincian yang usang ditolak, sama seperti dari daftar: tanpa baris baru.
        $this->actingAs($perencanaan)->from($rincian)
            ->post("/verifikasi/{$this->pengukuran->id}/status-capaian", [
                'pengukuran_versi_id' => $versi->id, 'status' => 'belum_tercapai', 'versi' => $token,
            ])
            ->assertRedirect($rincian)
            ->assertSessionHasErrors();
        $this->assertSame(1, DB::table('status_capaian')->count());
        $this->assertSame('tercapai', DB::table('status_capaian')->value('status'));
    }

    public function test_versi_koreksi_menampilkan_angka_versi_sah_terakhir_dengan_riwayat_versi_lama(): void
    {
        $versi1 = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $lama = $this->tetapkan($this->pengukuran->id, $versi1->id, 'tercapai');
        $versi2 = $this->sahkan($this->pengukuran, ['nilai' => '90']);

        $baris = $this->propsRincian($this->pengukuran->id)['pengukuran'];

        $this->assertSame($versi2->id, $baris['versi_pengukuran']['id']);
        $this->assertSame(90.0, (float) $baris['nilai']);
        $this->assertArrayNotHasKey('status_berlaku', $baris, 'Status versi lama tidak diwarisi versi koreksi.');
        $this->assertSame([$lama->id], array_column($baris['riwayat'], 'id'));
        $this->assertSame(1, $baris['riwayat'][0]['versi_nomor']);
        $this->assertSame(['tetapkan' => true, 'revisi' => false], $baris['can']);
    }

    public function test_rincian_ditolak_403_untuk_peran_tanpa_izin_status_capaian(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $this->buka($this->pengukuran->id, $this->userWithRole('perencanaan'))->assertOk();

        foreach (['admin', 'pimpinan', 'pegawai'] as $kode) {
            $this->buka($this->pengukuran->id, $this->userWithRole($kode))->assertForbidden();
        }
    }

    public function test_rincian_404_untuk_unit_yang_ditolak_per_unit(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $ditetapkan = $this->headerUntuk($this->indikatorBaru('I-SATUAN-SAMA'));
        $this->tetapkan($ditetapkan->id, $this->sahkan($ditetapkan, ['nilai' => '70'])->id, 'tercapai');
        $unitLain = $this->headerUntuk($this->indikatorBaru('I-LAIN', snapshot: ['unit_id' => $this->unitBaru('Unit Lain')->id]));
        $this->sahkan($unitLain, ['nilai' => '10']);
        $perencanaan = $this->userWithRole('perencanaan');

        // Tanpa deny ketiganya terbuka, agar 404 di bawah bukan efek fixture.
        $this->assertSame($versi->id, $this->propsRincian($this->pengukuran->id, $perencanaan)['pengukuran']['versi_pengukuran']['id']);
        $this->buka($ditetapkan->id, $perencanaan)->assertOk();
        $this->buka($unitLain->id, $perencanaan)->assertOk();

        $this->tolak($perencanaan, $this->unit->id);

        $this->buka($this->pengukuran->id, $perencanaan)->assertNotFound();
        $this->buka($ditetapkan->id, $perencanaan)->assertNotFound();
        $this->buka($unitLain->id, $perencanaan)->assertOk();
        // Deny melekat pada pengguna, bukan pada pengukuran.
        $this->buka($this->pengukuran->id)->assertOk();
    }

    public function test_rincian_404_untuk_header_yang_tidak_disahkan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($this->pengukuran->id, $versi->id, 'tercapai');
        $draft = $this->headerUntuk($this->indikatorBaru('I-DRAFT'));
        $this->ajukan($draft, ['nilai' => '10'], 'draft');

        $this->buka($this->pengukuran->id)->assertOk();
        $this->buka($draft->id)->assertNotFound();

        // Header dikembalikan: versi beku dan statusnya masih tersimpan, tetapi baris hilang dari kedua daftar.
        DB::table('pengukuran_kinerjas')->where('id', $this->pengukuran->id)->update(['status_alur' => 'dikembalikan']);

        $this->buka($this->pengukuran->id)->assertNotFound();
        $this->assertSame(1, DB::table('status_capaian')->where('pengukuran_id', $this->pengukuran->id)->count());
    }

    public function test_rincian_404_untuk_kondisi_yang_dikecualikan_dari_kandidat_selama_belum_punya_status(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->buka($this->pengukuran->id)->assertOk();

        $kondisi = [
            'I-TANPA-TARGET' => ['nilai' => '80', 'tanpa_target_periode' => true],
            'I-BELUM-DIUKUR' => ['nilai' => '80', 'status_perhitungan' => 'belum_dapat_diukur'],
            'I-TIDAK-DIHITUNG' => ['nilai' => '80', 'status_perhitungan' => 'tidak_dapat_dihitung', 'tanpa_target_periode' => false],
        ];

        foreach ($kondisi as $kode => $snapshot) {
            $header = $this->headerUntuk($this->indikatorBaru($kode));
            $versi = $this->sahkan($header, $snapshot);

            $this->buka($header->id)->assertNotFound();

            // Begitu versi itu punya status, baris tampil di daftar "sudah ditetapkan" dan rinciannya ikut terbuka,
            // tetapi tetap tidak dapat direvisi.
            StatusCapaian::create([
                'pengukuran_id' => $header->id, 'pengukuran_versi_id' => $versi->id, 'status' => 'tercapai',
                'sumber' => StatusCapaian::SUMBER_MANUAL, 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
            ]);

            $baris = $this->propsRincian($header->id)['pengukuran'];
            $this->assertSame('tercapai', $baris['status_berlaku']['status'], $kode);
            $this->assertSame(['tetapkan' => false, 'revisi' => false], $baris['can'], $kode);
        }
    }

    public function test_pengguna_nonaktif_ditolak(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->buka($this->pengukuran->id, $perencanaan)->assertOk();

        $perencanaan->update(['status' => 'nonaktif']);

        $this->buka($this->pengukuran->id, $perencanaan)->assertRedirect(route('auth.pending'));
    }

    public function test_alamat_rincian_hanya_menerima_uuid_dan_tidak_menggeser_route_lain(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $this->assertSame("/verifikasi/status-capaian/{$this->pengukuran->id}", route('status-capaian.show', $this->pengukuran->id, false));
        $this->buka($this->pengukuran->id)->assertOk();
        $this->buka('bukan-uuid')->assertNotFound();
        $this->buka((string) Str::uuid())->assertNotFound();

        $rute = fn (string $path): ?string => app('router')->getRoutes()->match(Request::create($path))->getName();
        $this->assertSame('status-capaian.show', $rute("/verifikasi/status-capaian/{$this->pengukuran->id}"));
        $this->assertSame('status-capaian.index', $rute('/verifikasi/status-capaian'));
        $this->assertSame('verifikasi.show', $rute("/verifikasi/{$this->pengukuran->id}"));
    }

    public function test_angka_rincian_berasal_dari_versi_sah_yang_dibekukan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80', 'target' => '100', 'target_pk' => '90']);
        $beku = json_decode((string) DB::table('pengukuran_versi')->where('id', $versi->id)->value('snapshot'), true);

        $sebelum = $this->propsRincian($this->pengukuran->id)['pengukuran'];

        $this->assertSame((float) $beku['nilai'], (float) $sebelum['nilai']);
        $this->assertSame((float) $beku['target'], (float) $sebelum['target']);
        $this->assertSame((float) $beku['target_pk'], (float) $sebelum['target_pk']);
        $this->assertSame(80.0, (float) $sebelum['capaian_persen']);
        $this->assertSame(-10.0, (float) $sebelum['selisih_target_pk']);

        // Sumber hidup diubah setelah versi disahkan; rincian tidak boleh ikut berubah.
        $terdampak = DB::table('indikator_kinerjas')->update([
            'nama' => 'Nama Indikator Diubah', 'satuan' => 'satuan-diubah', 'arah' => 'turun_baik', 'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $terdampak);
        DB::table('target_kinerjas')->update(['target_tahunan' => 111, 'target_tw1' => 111, 'updated_at' => now()]);
        DB::table('unit')->update(['nama' => 'Unit Diubah']);

        $this->assertSame($sebelum, $this->propsRincian($this->pengukuran->id)['pengukuran']);
    }
}
