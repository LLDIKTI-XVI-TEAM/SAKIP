<?php

namespace Tests\Feature\Pengukuran;

use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesDashboardFixture;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

/**
 * Antrean `/verifikasi/disahkan` (Plan 6.3, keputusan K1): halaman terpisah yang hanya memuat
 * pengukuran `disahkan`, digerbangi `pengukuran:buka_kembali`, dipangkas deny per unit, dan
 * menyajikan identitas versi resmi per baris beserta filter yang dapat dipilih.
 */
class IndexPengukuranDisahkanTest extends TestCase
{
    use CreatesDashboardFixture, CreatesPengukuranFixture, RefreshDatabase;

    private const HALAMAN = '/verifikasi/disahkan';

    /**
     * Payload beku lengkap agar PresentPengukuran dapat membaca versi resmi tanpa key hilang;
     * nilainya tidak diuji di sini, hanya bentuk antreannya.
     *
     * @return array<string, mixed>
     */
    private function payload(PengukuranKinerja $pengukuran): array
    {
        $konteks = $pengukuran->jadwalSnapshot;

        return [
            'nilai' => '80', 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'manual', 'catatan' => null,
            'alasan_tidak_dapat_dihitung' => null,
            'progres_kegiatan' => 'Progres resmi.', 'kendala_masalah' => 'Kendala resmi.',
            'strategi_tindak_lanjut' => 'Strategi resmi.', 'tindak_lanjut_rekomendasi' => null,
            'indikator' => [...$konteks->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']),
                'id' => $pengukuran->indikator_id, 'kode' => $pengukuran->indikator->kode],
            'unit_kerja' => $konteks->unit->only(['id', 'nama']),
            'pic' => $this->actor->only(['id', 'nama']),
            'periode' => $pengukuran->periode->only(['id', 'nama', 'urutan', 'is_nilai_akhir']),
            'target' => 70, 'target_pk' => 70, 'tanpa_target_periode' => false, 'rekomendasi_sebelumnya' => null,
            'komponen' => [], 'klaim' => [], 'persyaratan_bukti' => [], 'bukti_dukungs' => [],
        ];
    }

    /** Versi resmi sah dan header `disahkan`; header ikut berpindah status seperti alur pengesahan. */
    private function sahkanHeader(PengukuranKinerja $pengukuran): KinerjaSnapshot
    {
        return $this->sahkan($pengukuran, $this->payload($pengukuran));
    }

    /** Baris disahkan pada unit lain: indikator dan snapshot jadwal sendiri agar unit target berbeda. */
    private function barisUnit(Unit $unit, string $kode): PengukuranKinerja
    {
        $header = $this->headerUntuk($this->indikatorBaru($kode, [], ['unit_id' => $unit->id]));
        $this->sahkanHeader($header);

        return $header->fresh();
    }

    /**
     * Sesi koreksi resmi pada jadwal fixture. Jendela waktu eksplisit agar kelayakan jadwal diuji pada
     * tanggal tetap, bukan bergantung pada tanggal berjalan.
     *
     * @param  array<string, mixed>|null  $lingkup
     */
    private function sesiKoreksi(string $mulai, string $sampai, ?array $lingkup): void
    {
        $this->jadwal->update(['koreksi_mulai' => $mulai, 'koreksi_sampai' => $sampai, 'lingkup_koreksi' => $lingkup]);
    }

    /** Daftar dan ketiga lookup berbagi scope yang sama: baris di luar kelayakan jadwal tidak boleh bocor. */
    private function assertTersembunyi(User $aktor): void
    {
        $this->actingAs($aktor)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 0)->where('pagination.total', 0));

        foreach (['unit', 'periode', 'renstra'] as $jenis) {
            $this->actingAs($aktor)->getJson(self::HALAMAN.'/pilihan?jenis='.$jenis)->assertOk()
                ->assertJsonCount(0, 'data')->assertJsonPath('has_more', false);
        }
    }

    /** Baris fixture tetap terbaca pada daftar dan ketiga lookup saat jadwalnya layak dikoreksi. */
    private function assertTampil(User $aktor, PengukuranKinerja $baris): void
    {
        $this->actingAs($aktor)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)->where('pengukurans.0.id', $baris->id)->where('pagination.total', 1));

        $harapan = ['unit' => $this->unit->id, 'periode' => $baris->periode_id, 'renstra' => $this->jadwal->renstra_id];
        foreach ($harapan as $jenis => $id) {
            $this->actingAs($aktor)->getJson(self::HALAMAN.'/pilihan?jenis='.$jenis)->assertOk()
                ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        }
    }

    public function test_perencanaan_mendapat_antrean_disahkan_dengan_props_lengkap(): void
    {
        $versi = $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->component('Verifikasi/Disahkan')
            ->has('pengukurans', 1)
            ->where('pengukurans.0.id', $this->pengukuran->id)
            ->where('pengukurans.0.status', 'disahkan')
            // Bentuk versi resmi hanya identitas minimal: id, nomor, waktu pengesahan.
            ->has('pengukurans.0.versi_resmi', 3)
            ->where('pengukurans.0.versi_resmi.id', $versi->id)
            ->where('pengukurans.0.versi_resmi.nomor', 1)
            ->where('pengukurans.0.versi_resmi.disahkan_pada', $versi->disahkan_at->toIso8601String())
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 1)
            ->where('pagination.total', 1)
            ->where('filters.renstra_id', '')
            ->where('filters.tahun', '')
            ->where('filters.periode_id', '')
            ->where('filters.unit_id', '')
            ->where('selected_filters.renstra_id', null)
            ->where('selected_filters.periode_id', null)
            ->where('selected_filters.unit_id', null)
            ->where('can.buka_kembali', true));
    }

    public function test_pegawai_tanpa_izin_ditolak_403_pada_halaman_dan_pilihan(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $pegawai = $this->userWithRole('pegawai');

        // Tanpa `pengukuran:buka_kembali` halaman tidak boleh terbuka, termasuk lookup filternya.
        $this->actingAs($pegawai)->get(self::HALAMAN)->assertForbidden();
        $this->actingAs($pegawai)->getJson(self::HALAMAN.'/pilihan?jenis=unit')->assertForbidden();
    }

    public function test_antrean_hanya_memuat_pengukuran_disahkan(): void
    {
        $versi = $this->sahkanHeader($this->pengukuran);

        $periodeKedua = Periode::findOrFail($this->tambahPeriode(2, '2026-04-01', '2026-06-30')->periode_id);
        $diajukan = $this->headerUntuk($this->context, $periodeKedua->id);
        $this->ajukan($diajukan, $this->payload($diajukan));

        $draf = $this->headerUntuk($this->indikatorBaru('I-DRAF'));
        $this->assertSame('draft', $draf->fresh()->status_alur);

        $this->actingAs($this->userWithRole('perencanaan'))->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)
            ->where('pengukurans.0.id', $this->pengukuran->id)
            ->where('pengukurans.0.versi_resmi.id', $versi->id)
            ->where('pagination.total', 1));
    }

    public function test_penutupan_lewat_tanpa_sesi_koreksi_menghilangkan_baris_dari_daftar_dan_pilihan(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // `penutupan` 2026-12-31 sudah lewat dan tidak ada sesi koreksi: jadwal tidak lagi layak dikoreksi.
        $this->assertTersembunyi($perencanaan);
    }

    public function test_sesi_koreksi_aktif_yang_mencakup_pengukuran_menampilkan_baris_pascapenutupan(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->sesiKoreksi('2027-01-01 00:00:00', '2027-01-31 23:59:59', ['jenis_objek' => ['pengukuran'],
            'indikator_ids' => [$this->pengukuran->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]]);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // Sesi resmi yang mencakup `pengukuran` membuka kembali baris walau tahun sudah ditutup.
        $this->assertTampil($perencanaan, $this->pengukuran);
    }

    public function test_sesi_koreksi_yang_hanya_mencakup_rencana_aksi_tidak_membuka_baris_pengukuran(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->sesiKoreksi('2027-01-01 00:00:00', '2027-01-31 23:59:59', ['jenis_objek' => ['rencana_aksi'],
            'indikator_ids' => [$this->pengukuran->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]]);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // Jendela aktif tetapi lingkupnya milik objek lain: pengukuran tetap tertutup (fail-closed).
        $this->assertTersembunyi($perencanaan);
    }

    public function test_sesi_koreksi_yang_sudah_berakhir_tidak_membuka_baris(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->sesiKoreksi('2026-11-01 00:00:00', '2026-12-31 23:59:59', ['jenis_objek' => ['pengukuran'],
            'indikator_ids' => [$this->pengukuran->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]]);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // Batas atas jendela wajib dihormati: sesi yang sudah lewat tidak lagi membuka baris.
        $this->assertTersembunyi($perencanaan);
    }

    public function test_sesi_koreksi_yang_belum_dimulai_tidak_membuka_baris(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->sesiKoreksi('2027-02-01 00:00:00', '2027-02-28 23:59:59', ['jenis_objek' => ['pengukuran'],
            'indikator_ids' => [$this->pengukuran->indikator_id], 'periode_ids' => [$this->pengukuran->periode_id]]);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // Batas bawah jendela wajib dihormati: sesi yang belum dimulai tidak membuka baris.
        $this->assertTersembunyi($perencanaan);
    }

    public function test_lingkup_koreksi_null_tidak_membuka_baris_pascapenutupan(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->sesiKoreksi('2027-01-01 00:00:00', '2027-01-31 23:59:59', null);
        $this->travelTo(now()->setDate(2027, 1, 5)->setTime(9, 0));

        // Jendela aktif tanpa lingkup tidak menyatakan objek mana pun termasuk: tetap tertutup (fail-closed).
        $this->assertTersembunyi($perencanaan);
    }

    public function test_jadwal_aktif_sebelum_penutupan_tetap_menampilkan_baris(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $perencanaan = $this->userWithRole('perencanaan');
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(9, 0));

        // Regresi skenario existing: jadwal `aktif` dengan `penutupan` 2026-12-31 belum lewat pada 2026-10-30.
        $this->assertTampil($perencanaan, $this->pengukuran);
    }

    public function test_deny_unit_menyembunyikan_baris_unit_tertentu_saja(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $unitLain = $this->unitBaru('Unit Lain');
        $barisLain = $this->barisUnit($unitLain, 'I-LAIN');

        $perencanaan = $this->userWithRole('perencanaan');

        // Pembanding: tanpa deny kedua unit terbaca lewat halaman yang sama.
        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengukurans', 2)->where('pagination.total', 2));

        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $perencanaan->id,
            'permission_id' => Permission::where('kode', 'pengukuran:buka_kembali')->value('id'),
            'unit_id' => $this->unit->id, 'alasan' => 'Penolakan sintetis',
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);

        $this->actingAs($perencanaan)->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)
            ->where('pengukurans.0.id', $barisLain->id)
            ->where('pagination.total', 1));

        // Lookup filter memakai scope yang sama: unit ter-deny tidak ditawarkan lagi.
        $this->actingAs($perencanaan)->getJson(self::HALAMAN.'/pilihan?jenis=unit')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $unitLain->id);
    }

    public function test_versi_resmi_null_untuk_header_disahkan_tanpa_versi_sah(): void
    {
        // Alur normal tidak pernah menyisakan `disahkan` tanpa versi sah; baris disiapkan langsung
        // agar cabang defensif antrean terbukti tidak mengarang identitas versi resmi.
        $this->pengukuran->forceFill(['status_alur' => 'disahkan'])->save();

        $this->actingAs($this->userWithRole('perencanaan'))->get(self::HALAMAN)->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)
            ->where('pengukurans.0.id', $this->pengukuran->id)
            ->where('pengukurans.0.versi_resmi', null));
    }

    public function test_filter_periode_dan_tahun_menyaring_antrean(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $periodeKedua = Periode::findOrFail($this->tambahPeriode(2, '2026-04-01', '2026-06-30')->periode_id);
        $barisKedua = $this->headerUntuk($this->context, $periodeKedua->id);
        $this->sahkanHeader($barisKedua);

        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['periode_id' => $this->pengukuran->periode_id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 1)
            ->where('pengukurans.0.id', $this->pengukuran->id)
            ->where('filters.periode_id', $this->pengukuran->periode_id)
            ->where('selected_filters.periode_id.id', $this->pengukuran->periode_id)
            ->where('selected_filters.periode_id.label', 'Triwulan I'));

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['periode_id' => $periodeKedua->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 1)
            ->where('pengukurans.0.id', $barisKedua->id)
            ->where('selected_filters.periode_id.id', $periodeKedua->id)
            ->where('selected_filters.periode_id.label', 'Triwulan II'));

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?tahun=2026')->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengukurans', 2)->where('filters.tahun', '2026'));

        // Tahun lain tidak punya baris: filter benar-benar menyaring, bukan diabaikan.
        $this->actingAs($perencanaan)->get(self::HALAMAN.'?tahun=2025')->assertOk()
            ->assertInertia(fn ($page) => $page->has('pengukurans', 0)->where('filters.tahun', '2025'));
    }

    public function test_filter_unit_dan_renstra_menyaring_dan_id_luar_scope_memberi_label_null(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $unitLain = $this->unitBaru('Unit Lain');
        $barisLain = $this->barisUnit($unitLain, 'I-LAIN');
        $unitKosong = $this->unitBaru('Unit Tanpa Antrean');

        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['unit_id' => $unitLain->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 1)
            ->where('pengukurans.0.id', $barisLain->id)
            ->where('filters.unit_id', $unitLain->id)
            ->where('selected_filters.unit_id.id', $unitLain->id)
            ->where('selected_filters.unit_id.label', 'Unit Lain'));

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['renstra_id' => $this->jadwal->renstra_id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 2)
            ->where('filters.renstra_id', $this->jadwal->renstra_id)
            ->where('selected_filters.renstra_id.id', $this->jadwal->renstra_id)
            ->where('selected_filters.renstra_id.label', 'Renstra Uji'));

        // Unit tanpa antrean ada di master tetapi di luar scope antrean: tanpa baris dan tanpa label.
        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['unit_id' => $unitKosong->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 0)
            ->where('filters.unit_id', $unitKosong->id)
            ->where('selected_filters.unit_id', null));

        $renstraAsing = (string) Str::uuid();
        $this->actingAs($perencanaan)->get(self::HALAMAN.'?'.http_build_query(['renstra_id' => $renstraAsing]))
            ->assertOk()->assertInertia(fn ($page) => $page->has('pengukurans', 0)
            ->where('filters.renstra_id', $renstraAsing)
            ->where('selected_filters.renstra_id', null));
    }

    public function test_pilihan_jenis_unit_periode_renstra_mengikuti_scope_dan_pencarian(): void
    {
        $this->sahkanHeader($this->pengukuran);
        $this->unitBaru('Unit Tanpa Antrean');
        $perencanaan = $this->userWithRole('perencanaan');

        $harapan = ['unit' => $this->unit->id, 'periode' => $this->pengukuran->periode_id, 'renstra' => $this->jadwal->renstra_id];
        foreach ($harapan as $jenis => $id) {
            $this->actingAs($perencanaan)->getJson(self::HALAMAN.'/pilihan?jenis='.$jenis)->assertOk()
                ->assertJsonStructure(['data' => ['*' => ['id', 'label']], 'page', 'has_more'])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $id)
                ->assertJsonPath('page', 1)
                ->assertJsonPath('has_more', false);
        }

        $this->actingAs($perencanaan)->getJson(self::HALAMAN.'/pilihan?jenis=unit&q=Unit%20Pengujian')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.label', 'Unit Pengujian');

        $this->actingAs($perencanaan)->getJson(self::HALAMAN.'/pilihan?jenis=unit&q=Tidak%20Ada')->assertOk()
            ->assertJsonCount(0, 'data');

        // `%` di-escape sebagai literal, bukan wildcard pola pencarian.
        $this->actingAs($perencanaan)->getJson(self::HALAMAN.'/pilihan?jenis=unit&q=%25')->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_paginasi_dua_puluh_baris_dan_query_string_dipertahankan(): void
    {
        foreach (range(1, 21) as $nomor) {
            $this->sahkanHeader($this->headerUntuk($this->indikatorBaru(sprintf('I-PAGI-%02d', $nomor))));
        }
        $perencanaan = $this->userWithRole('perencanaan');

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?tahun=2026')->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 20)
            ->where('pagination.current_page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 21)
            ->where('pagination.prev_page_url', null)
            // withQueryString: filter aktif ikut pada tautan halaman berikutnya.
            ->where('pagination.next_page_url', fn ($url) => is_string($url)
                && str_contains($url, 'tahun=2026') && str_contains($url, 'page=2')));

        $this->actingAs($perencanaan)->get(self::HALAMAN.'?tahun=2026&page=2')->assertOk()->assertInertia(fn ($page) => $page
            ->has('pengukurans', 1)
            ->where('pagination.current_page', 2)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 21));
    }
}
