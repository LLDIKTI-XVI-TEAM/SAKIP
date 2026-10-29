<?php

namespace Tests\Feature\StatusCapaian;

use App\Actions\StatusCapaian\TetapkanStatusCapaian;
use App\Models\Permission;
use App\Models\StatusCapaian;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Kontrak halaman verifikasi status capaian: dua koleksi (`kandidat` dan `sudah_ditetapkan`) yang dibangun dari
 * versi sah terakhir, bukan dari rencana aksi hidup.
 */
class IndexStatusCapaianTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    /** @return array<string, mixed> */
    private function props(?User $sebagai = null): array
    {
        return $this->actingAs($sebagai ?? $this->actor)
            ->get('/verifikasi/status-capaian')
            ->assertOk()
            ->viewData('page')['props'];
    }

    private function tetapkan(string $pengukuranId, string $versiId, string $status): StatusCapaian
    {
        return app(TetapkanStatusCapaian::class)->handle($this->actor, $pengukuranId, [
            'pengukuran_versi_id' => $versiId,
            'status' => $status,
            // Token konkurensi dibaca dari basis data agar tidak bergantung instance fixture yang belum menyegarkan kolom.
            'versi' => (int) DB::table('pengukuran_kinerjas')->where('id', $pengukuranId)->value('versi'),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function baris(array $rows, string $pengukuranId): ?array
    {
        return collect($rows)->firstWhere('id', $pengukuranId);
    }

    /** @return array<string, mixed> */
    private function snapshotBeku(string $versiId): array
    {
        $snapshot = DB::table('pengukuran_versi')->where('id', $versiId)->value('snapshot');

        return is_string($snapshot) ? (array) json_decode($snapshot, true) : (array) $snapshot;
    }

    private function tolak(string $unitId): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', PermissionCodes::STATUS_CAPAIAN_UPDATE)->value('id'),
            'unit_id' => $unitId,
            'alasan' => 'Deny pengujian',
            'ditetapkan_oleh' => $this->actor->id,
            'created_at' => now(),
        ]);
    }

    public function test_kandidat_memuat_versi_sah_terakhir_yang_belum_punya_status(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $props = $this->props();

        $this->assertTrue($props['can']['tetapkan']);
        $this->assertSame([], $props['sudah_ditetapkan'], 'Belum ada status, koleksi revisi harus kosong.');

        $baris = $this->baris($props['kandidat'], $this->pengukuran->id);
        $this->assertNotNull($baris, 'Pengukuran disahkan tanpa status harus muncul sebagai kandidat.');
        $this->assertSame($versi->id, $baris['versi_pengukuran']['id']);
        $this->assertSame(1, $baris['versi_pengukuran']['nomor']);
        $this->assertArrayNotHasKey('status_berlaku', $baris);
        $this->assertTrue($baris['can']['tetapkan']);
        $this->assertFalse($baris['can']['revisi']);
    }

    public function test_penetapan_memindahkan_baris_ke_sudah_ditetapkan(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $status = $this->tetapkan($this->pengukuran->id, $versi->id, 'tercapai');

        $props = $this->props();

        $this->assertSame([], $props['kandidat'], 'Versi yang sudah berstatus tidak boleh tetap menjadi kandidat.');

        $baris = $this->baris($props['sudah_ditetapkan'], $this->pengukuran->id);
        $this->assertNotNull($baris);
        $this->assertSame('tercapai', $baris['status_berlaku']['status']);
        $this->assertSame($status->id, $baris['status_berlaku']['id']);
        $this->assertSame('manual', $baris['status_berlaku']['sumber']);
        $this->assertSame($versi->id, $baris['status_berlaku']['versi_id']);
        $this->assertSame(1, $baris['status_berlaku']['versi_nomor']);
        $this->assertTrue($baris['can']['revisi']);
        $this->assertFalse($baris['can']['tetapkan']);

        $this->assertCount(1, $baris['riwayat']);
        $this->assertSame('tercapai', $baris['riwayat'][0]['status']);
        $this->assertSame($versi->id, $baris['riwayat'][0]['versi_id']);
        $this->assertSame($this->actor->nama, $baris['riwayat'][0]['penetap']);
    }

    public function test_versi_koreksi_disahkan_mengembalikan_baris_ke_kandidat_tanpa_menghapus_riwayat(): void
    {
        $versi1 = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($this->pengukuran->id, $versi1->id, 'tercapai');

        $versi2 = $this->sahkan($this->pengukuran, ['nilai' => '90']);

        $props = $this->props();

        $this->assertSame([], $props['sudah_ditetapkan'], 'Status versi lama tidak boleh diwarisi versi koreksi.');

        $baris = $this->baris($props['kandidat'], $this->pengukuran->id);
        $this->assertNotNull($baris, 'Versi koreksi yang belum berstatus harus kembali menjadi kandidat.');
        $this->assertSame($versi2->id, $baris['versi_pengukuran']['id']);
        $this->assertSame(2, $baris['versi_pengukuran']['nomor']);
        $this->assertTrue($baris['can']['tetapkan']);
        $this->assertArrayNotHasKey('status_berlaku', $baris);

        $this->assertSame(1, DB::table('status_capaian')->where('pengukuran_versi_id', $versi1->id)->count());
        $this->assertSame(1, DB::table('status_capaian')->count());
    }

    public function test_kandidat_menyaring_kondisi_yang_dilarang_ac_5(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $tanpaTarget = $this->headerUntuk($this->indikatorBaru('I-TANPA-TARGET'));
        $this->sahkan($tanpaTarget, ['nilai' => '80', 'tanpa_target_periode' => true]);

        $belumDiukur = $this->headerUntuk($this->indikatorBaru('I-BELUM-DIUKUR'));
        $this->sahkan($belumDiukur, ['nilai' => '80', 'status_perhitungan' => 'belum_dapat_diukur']);

        $tidakDihitung = $this->headerUntuk($this->indikatorBaru('I-TIDAK-DIHITUNG'));
        $this->sahkan($tidakDihitung, [
            'nilai' => '80',
            'status_perhitungan' => 'tidak_dapat_dihitung',
            'tanpa_target_periode' => false,
        ]);

        $props = $this->props();

        $this->assertNotNull($this->baris($props['kandidat'], $this->pengukuran->id), 'Pengukuran normal tetap kandidat.');
        $this->assertNull($this->baris($props['kandidat'], $tanpaTarget->id), 'Tanpa target periode tidak boleh masuk kandidat.');
        $this->assertNull($this->baris($props['kandidat'], $belumDiukur->id), 'belum_dapat_diukur tidak boleh masuk kandidat.');
        $this->assertNull($this->baris($props['kandidat'], $tidakDihitung->id), 'tidak_dapat_dihitung tidak boleh masuk kandidat.');
        $this->assertCount(1, $props['kandidat']);
        $this->assertSame(1, $props['pagination']['total']);
    }

    public function test_header_non_disahkan_tidak_muncul_di_kedua_koleksi(): void
    {
        $this->ajukan($this->pengukuran, [], 'draft');

        $lain = $this->headerUntuk($this->indikatorBaru('I-SAH'));
        $this->sahkan($lain, ['nilai' => '10']);

        $props = $this->props();

        $this->assertNull($this->baris($props['kandidat'], $this->pengukuran->id));
        $this->assertNull($this->baris($props['sudah_ditetapkan'], $this->pengukuran->id));
        $this->assertNotNull($this->baris($props['kandidat'], $lain->id));
        $this->assertCount(1, $props['kandidat']);
        $this->assertSame([], $props['sudah_ditetapkan']);
    }

    public function test_deny_unit_menyembunyikan_baris_pada_kedua_koleksi(): void
    {
        $unitUtama = $this->pengukuran->targetUnitId();

        $versiKandidat = $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $satuanSama = $this->indikatorBaru('I-SATUAN-SAMA', snapshot: ['unit_id' => $unitUtama]);
        $headerSatuanSama = $this->headerUntuk($satuanSama);
        $versiSatuanSama = $this->sahkan($headerSatuanSama, ['nilai' => '70']);
        $this->tetapkan($headerSatuanSama->id, $versiSatuanSama->id, 'tercapai');

        $unitLain = $this->unitBaru('Unit Lain');
        $headerLain = $this->headerUntuk($this->indikatorBaru('I-LAIN', snapshot: ['unit_id' => $unitLain->id]));
        $this->sahkan($headerLain, ['nilai' => '10']);

        // Tanpa deny kedua baris unit utama harus terlihat, agar penolakan di bawah bukan efek fixture kosong.
        $sebelum = $this->props();
        $this->assertNotNull($this->baris($sebelum['kandidat'], $this->pengukuran->id));
        $this->assertSame($versiKandidat->id, $this->baris($sebelum['kandidat'], $this->pengukuran->id)['versi_pengukuran']['id']);
        $this->assertNotNull($this->baris($sebelum['sudah_ditetapkan'], $headerSatuanSama->id));

        $this->tolak($unitUtama);

        $props = $this->props();

        $this->assertNull($this->baris($props['kandidat'], $this->pengukuran->id), 'Kandidat unit yang di-deny harus hilang.');
        $this->assertNull($this->baris($props['sudah_ditetapkan'], $headerSatuanSama->id), 'Koleksi revisi unit yang di-deny harus hilang.');
        $this->assertNotNull($this->baris($props['kandidat'], $headerLain->id), 'Unit lain harus tetap muncul.');
        $this->assertCount(1, $props['kandidat']);
        $this->assertSame([], $props['sudah_ditetapkan']);
    }

    public function test_pagination_hanya_menghitung_kandidat_dan_can_mengikuti_izin(): void
    {
        $this->sahkan($this->pengukuran, ['nilai' => '80']);

        $lain = $this->headerUntuk($this->indikatorBaru('I-REVISI'));
        $versiLain = $this->sahkan($lain, ['nilai' => '10']);
        $this->tetapkan($lain->id, $versiLain->id, 'tercapai');

        $props = $this->props();

        $this->assertCount(1, $props['kandidat']);
        $this->assertCount(1, $props['sudah_ditetapkan']);
        $this->assertSame(1, $props['pagination']['total'], 'Total pagination hanya milik koleksi kandidat.');
        $this->assertSame(1, $props['pagination']['current_page']);
        $this->assertSame(1, $props['pagination']['last_page']);
        $this->assertTrue($props['can']['tetapkan']);

        // Factory default `nonaktif` akan dialihkan middleware sebelum policy sempat menolak; pakai user aktif tanpa peran.
        $tanpaIzin = User::factory()->create(['status' => 'aktif']);
        $this->assertFalse(
            app(PermissionResolver::class)->allows($tanpaIzin, PermissionCodes::STATUS_CAPAIAN_UPDATE),
            'Fixture user tanpa peran tidak boleh memiliki izin status capaian.',
        );
        $this->actingAs($tanpaIzin)->get('/verifikasi/status-capaian')->assertForbidden();
    }

    /** P2: koleksi revisi dipaginasi dengan parameter `status_page` sendiri agar tidak berebut `page` kandidat. */
    public function test_pagination_status_memakai_parameter_halaman_terpisah(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80']);
        $this->tetapkan($this->pengukuran->id, $versi->id, 'tercapai');

        $props = $this->props();

        $this->assertSame([
            'current_page' => 1, 'last_page' => 1, 'total' => 1, 'prev_page_url' => null, 'next_page_url' => null,
        ], $props['pagination_status'], 'Props paginasi revisi harus berbentuk sama dengan paginasi kandidat.');
        $this->assertSame(0, $props['pagination']['total'], 'Pagination kandidat tidak boleh ikut menghitung koleksi revisi.');

        $halamanRevisi = $this->actingAs($this->actor)->get('/verifikasi/status-capaian?status_page=2')->assertOk()->viewData('page')['props'];
        $this->assertSame(2, $halamanRevisi['pagination_status']['current_page']);
        $this->assertSame([], $halamanRevisi['sudah_ditetapkan'], 'Halaman kedua koleksi revisi kosong bila hanya ada satu baris.');
        $this->assertSame(1, $halamanRevisi['pagination']['current_page'], 'Halaman kandidat tidak boleh ikut bergeser.');

        $halamanKandidat = $this->actingAs($this->actor)->get('/verifikasi/status-capaian?page=2')->assertOk()->viewData('page')['props'];
        $this->assertSame(2, $halamanKandidat['pagination']['current_page']);
        $this->assertSame(1, $halamanKandidat['pagination_status']['current_page'], 'Halaman revisi tidak boleh ikut bergeser.');
    }

    public function test_angka_diambil_dari_snapshot_beku_bukan_rencana_aksi_hidup(): void
    {
        $versi = $this->sahkan($this->pengukuran, ['nilai' => '80', 'target' => '100', 'target_pk' => '90']);
        $beku = $this->snapshotBeku($versi->id);

        $this->assertSame('80', (string) $beku['nilai']);
        $this->assertSame('100', (string) $beku['target']);
        $this->assertSame('90', (string) $beku['target_pk']);

        $sebelum = $this->baris($this->props()['kandidat'], $this->pengukuran->id);
        $this->assertNotNull($sebelum);

        $this->assertSame((float) $beku['nilai'], (float) $sebelum['nilai']);
        $this->assertSame((float) $beku['target'], (float) $sebelum['target']);
        $this->assertSame((float) $beku['target_pk'], (float) $sebelum['target_pk']);
        $this->assertSame(
            (float) $beku['nilai'] - (float) $beku['target_pk'],
            (float) $sebelum['selisih_target_pk'],
            'Selisih terhadap PK dihitung dari angka snapshot beku.',
        );

        // Ubah sumber hidup setelah versi disahkan: arah indikator, identitas indikator/unit, dan target tahunan.
        // Bila angka dihitung ulang dari sumber hidup, rasio capaian dan label baris akan ikut berubah.
        $terdampak = DB::table('indikator_kinerjas')->update([
            'nama' => 'Nama Indikator Diubah',
            'satuan' => 'satuan-diubah',
            'arah' => 'turun_baik',
            'updated_at' => now(),
        ]);
        $this->assertGreaterThan(0, $terdampak, 'Mutasi sumber hidup harus benar-benar mengenai baris indikator.');

        DB::table('target_kinerjas')->update(['target_tahunan' => 111, 'target_tw1' => 111, 'updated_at' => now()]);
        DB::table('unit')->update(['nama' => 'Unit Diubah']);

        $sesudah = $this->baris($this->props()['kandidat'], $this->pengukuran->id);
        $this->assertNotNull($sesudah);
        $this->assertSame($sebelum, $sesudah, 'Seluruh angka baris harus tetap berasal dari snapshot versi beku.');
    }
}
