<?php

namespace Tests\Feature\Laporan;

use App\Actions\Laporan\EksporRekapIndikatorPeriode;
use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\KinerjaSnapshot;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\RekomendasiPimpinan;
use App\Models\RencanaAksiVersi;
use App\Models\StatusCapaian;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class RekapIndikatorPeriodeTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    private function versi(array $data = [], bool $sah = true, int $nomor = 1, ?RencanaAksiVersi $ra = null): KinerjaSnapshot
    {
        return KinerjaSnapshot::create([
            'pengukuran_id' => $this->pengukuran->id, 'rencana_aksi_versi_id' => ($ra ?? $this->planVersion)->id,
            'jadwal_snapshot_id' => $this->context->id, 'nomor' => $nomor,
            'diajukan_by' => $this->actor->id, 'diajukan_at' => now(), 'jalur_pengajuan' => 'perencanaan',
            'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'disahkan_by' => $sah ? $this->actor->id : null, 'disahkan_at' => $sah ? now() : null,
            'snapshot' => [
                'indikator' => [...$this->context->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']), 'kode' => 'I-BEKU'],
                'unit_kerja' => ['id' => $this->unit->id, 'nama' => 'Unit beku'],
                'sasaran' => ['id' => $this->pengukuran->indikator->sasaran_strategis_id, 'kode' => 'S-BEKU', 'deskripsi' => 'Sasaran beku'],
                'pic' => $this->actor->only(['id', 'nama']),
                'periode' => ['id' => $this->pengukuran->periode_id, 'nama' => 'Triwulan I', 'urutan' => 1],
                'nilai' => '30', 'target' => '20', 'target_pk' => '70', 'tanpa_target_periode' => false,
                'status_perhitungan' => 'terhitung', 'alasan_tidak_dapat_dihitung' => null,
                'progres_kegiatan' => 'Progres resmi', 'kendala_masalah' => 'Kendala resmi',
                'strategi_tindak_lanjut' => 'Strategi resmi', 'tindak_lanjut_rekomendasi' => 'Tindak lanjut resmi',
                'komponen' => [], 'klaim' => [], 'persyaratan_bukti' => [], 'bukti_dukungs' => [],
                ...$data,
            ],
        ]);
    }

    /**
     * Status capaian manual pada versi resmi; pola fixture sama dengan tests/Feature/StatusCapaian.
     * Data Model §2.21: satu versi menyimpan riwayat, baris terbaru (created_at, lalu id) yang berlaku.
     */
    private function tetapkanStatus(KinerjaSnapshot $versi, string $status): StatusCapaian
    {
        return StatusCapaian::create([
            'pengukuran_id' => $this->pengukuran->id, 'pengukuran_versi_id' => $versi->id,
            'status' => $status, 'sumber' => 'manual', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
    }

    private function url(array $filter = []): string
    {
        return '/laporan/rekap?'.http_build_query(['jadwal_id' => $this->jadwal->id, ...$filter]);
    }

    private function deny(string $permission, ?string $unitId = null): void
    {
        DB::table('user_permission_denied')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', $permission)->value('id'), 'unit_id' => $unitId,
            'alasan' => 'Fixture pembatasan', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(),
        ]);
    }

    public function test_versi_sah_tetap_dibaca_ketika_koreksi_dan_master_berubah(): void
    {
        $resmi = $this->versi();
        $this->versi(['nilai' => '987654321', 'progres_kegiatan' => 'CANARY-VERSI-DRAF'], false, 2);
        $this->pengukuran->update(['nilai' => 987654321, 'status_perhitungan' => 'terhitung', 'progres_kegiatan' => 'CANARY-HEADER']);
        $this->pengukuran->indikator->update(['nama' => 'Nama master berubah', 'kode' => 'I-LIVE']);
        $pengganti = $this->userWithRole('pegawai');
        PenugasanIndikator::create(['indikator_id' => $this->pengukuran->indikator_id, 'user_id' => $pengganti->id,
            'tanggal_mulai_berlaku' => '2026-03-15', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $audit = AuditLog::count();

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->component('Laporan/Rekap')->has('baris', 1)
            ->where('baris.0.versi.id', $resmi->id)->where('baris.0.status_laporan', 'disahkan')
            ->where('baris.0.indikator.kode', 'I-BEKU')->where('baris.0.unit.nama', 'Unit beku')
            ->where('baris.0.sasaran.kode', 'S-BEKU')->where('baris.0.pic.id', $this->actor->id)
            ->where('baris.0.pic_saat_ini.id', $pengganti->id)->where('baris.0.nilai', '30')
            ->where('baris.0.target_periode', '20')->where('baris.0.target_pk', '70')
            ->where('baris.0.capaian_persen', '150.00')->where('baris.0.selisih_target_pk', '-40')
            ->where('baris.0.narasi.progres_kegiatan', 'Progres resmi'));
        $this->assertSame($audit, AuditLog::count());
    }

    public function test_baris_belum_sah_tidak_mengirim_data_draf_dan_semua_periode_efektif_tampil(): void
    {
        $next = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $next->id,
            'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']);
        $this->versi(['nilai' => '987654321', 'progres_kegiatan' => 'CANARY-RAHASIA'], false);
        $this->pengukuran->update(['nilai' => 987654321, 'status_perhitungan' => 'terhitung', 'progres_kegiatan' => 'CANARY-HEADER']);

        $response = $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 2)->where('baris.0.status_laporan', 'belum_disahkan')->where('baris.0.nilai', null)
            ->where('baris.0.target_periode', '70')->where('baris.0.target_pk', '70.000000000000')
            ->where('baris.0.capaian_persen', null)->where('baris.0.selisih_target_pk', null)
            ->where('baris.0.narasi', null)->where('baris.0.kegiatan', null)->where('baris.1.nilai', null));
        $response->assertDontSee('987654321')->assertDontSee('CANARY-');
        $this->get($this->url(['periode_id' => $next->id]))->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 1)->where('baris.0.periode.id', $next->id));
    }

    public function test_target_komponen_memakai_pasangan_ra_beku_bukan_ra_revisi(): void
    {
        $komponen = (string) Str::uuid();
        $ra = RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 2, 'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => '20', 'komponen' => [['komponen_id' => $komponen, 'nilai' => '12']]]]]]);
        $this->versi(['komponen' => [['komponen_id' => $komponen, 'kode' => 'A', 'label' => 'Komponen A', 'peran' => 'penjumlah', 'bobot' => '1', 'nilai' => '30']]], ra: $ra);
        RencanaAksiVersi::create([...$ra->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 3, 'snapshot' => ['target_periode' => [['periode_id' => $this->pengukuran->periode_id, 'nilai' => '999', 'komponen' => [['komponen_id' => $komponen, 'nilai' => '999']]]]]]);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.komponen.0.target', '12')->where('baris.0.komponen.0.realisasi', '30')
            ->where('baris.0.target_periode', '20'));
    }

    public function test_kegiatan_anggaran_dan_bukti_hanya_dari_snapshot_tanpa_path_privat(): void
    {
        $claim = ['id' => (string) Str::uuid(), 'kegiatan_id' => (string) Str::uuid(), 'sumber_klaim' => 'pengukuran',
            'kegiatan_bersama' => true, 'kegiatan' => ['nama' => 'Kegiatan resmi', 'anggaran' => '0', 'status' => 'tidak_terlaksana', 'kendala' => 'Kendala kegiatan beku',
                'bukti_dukungs' => [['path' => 'PRIVATE-PATH', 'isi_teks' => 'PRIVATE-ISI', 'tautan' => 'https://private.test']]]];
        $this->versi(['klaim' => [$claim], 'bukti_dukungs' => [['path' => 'PRIVATE-PATH']],
            'persyaratan_bukti' => [['nama' => 'Bukti wajib', 'wajib' => true, 'pemenuhan' => ['terpenuhi' => true, 'tidak_dapat_dipenuhi' => true]]]]);
        $response = $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.kegiatan.0.nama', 'Kegiatan resmi')->where('baris.0.kegiatan.0.anggaran', '0')
            ->where('baris.0.kegiatan.0.anggaran_tercatat', true)->where('baris.0.kegiatan.0.kegiatan_bersama', true)
            ->where('baris.0.bukti.status', 'lengkap')->where('baris.0.bukti.histori_tidak_dapat_dipenuhi', true));
        $response->assertDontSee('PRIVATE-')->assertDontSee('private.test');
        $this->deny('kegiatan:read', $this->unit->id);
        $this->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.kegiatan', null)->where('baris.0.can.lihat_kegiatan', false));
    }

    public function test_key_historis_hilang_tidak_diisi_dari_master_atau_anggaran_ra(): void
    {
        $ra = RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 2, 'snapshot' => ['klaim' => [['kegiatan_id' => 'kegiatan-uji', 'kegiatan' => ['anggaran' => '999999']]]]]);
        $version = $this->versi(['sasaran' => null, 'klaim' => [['kegiatan_id' => 'kegiatan-uji', 'kegiatan' => ['nama' => 'Kegiatan lama', 'status' => 'rencana']]]], ra: $ra);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.versi.id', $version->id)->where('baris.0.sasaran', null)
            ->where('baris.0.kegiatan.0.anggaran', null)->where('baris.0.kegiatan.0.anggaran_tercatat', false)
            ->where('baris.0.kegiatan.0.kegiatan_bersama', null));
    }

    public function test_sasaran_resmi_yang_tidak_tercatat_tidak_diisi_dari_pasangan_ra(): void
    {
        $ra = RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 2, 'snapshot' => ['sasaran' => ['id' => 'sasaran-versi-ra', 'kode' => 'S-RA', 'deskripsi' => 'Sasaran versi RA']]]);
        $this->versi(['sasaran' => null], ra: $ra);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('baris.0.sasaran', null));
    }

    public function test_ekspor_memerlukan_izin_baca_dan_ekspor_sebelum_lookup_filter(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->getJson('/laporan/rekap/ekspor?jadwal_id=invalid')->assertForbidden();
        $pegawai = $this->userWithRole('pegawai');
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $pegawai->id,
            'permission_id' => Permission::where('kode', 'laporan:ekspor')->value('id'), 'unit_id' => null,
            'alasan' => 'Fixture ekspor tanpa baca', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($pegawai)->getJson('/laporan/rekap/ekspor?jadwal_id=invalid')->assertForbidden();
    }

    public function test_ekspor_kosong_termasuk_semua_unit_dibatasi_tidak_membuat_audit(): void
    {
        $this->actingAs($this->actor)->getJson('/laporan/rekap/ekspor?tahun=2025')->assertUnprocessable()
            ->assertJsonPath('errors.ekspor.0', 'Tidak ada data untuk diekspor');
        $this->deny('laporan:ekspor', $this->unit->id);
        $this->getJson(str_replace('/rekap?', '/rekap/ekspor?', $this->url()))->assertUnprocessable()
            ->assertJsonPath('errors.ekspor.0', 'Tidak ada data untuk diekspor');
        $this->assertDatabaseMissing('audit_log', ['tindakan' => 'laporan.ekspor']);
    }

    private function ekspor(array $filter = []): Spreadsheet
    {
        $response = $this->actingAs($this->actor)->getJson(str_replace('/rekap?', '/rekap/ekspor?', $this->url($filter)));
        $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = tempnam(sys_get_temp_dir(), 'rekap-');
        try {
            file_put_contents($path, $response->streamedContent());

            return IOFactory::load($path);
        } finally {
            unlink($path);
        }
    }

    private function isiSheet(Spreadsheet $book, string $nama = 'Rekap'): array
    {
        $rows = $book->getSheetByName($nama)->toArray(null, false, false);
        $headers = $rows[7];

        return array_map(fn ($row) => array_combine($headers, $row), array_slice($rows, 8));
    }

    public function test_ekspor_memisahkan_nilai_komponen_narasi_kegiatan_dan_mencatat_audit_terbatas(): void
    {
        $version = $this->versi(['nilai' => '30.125', 'target' => '20.25', 'progres_kegiatan' => '=HYPERLINK("https://example.invalid","uji")',
            'komponen' => [['komponen_id' => (string) Str::uuid(), 'kode' => 'A', 'label' => 'Komponen A', 'peran' => 'penjumlah', 'bobot' => '1', 'nilai' => '12'],
                ['komponen_id' => (string) Str::uuid(), 'kode' => 'B', 'label' => 'Komponen B', 'peran' => 'penjumlah', 'bobot' => '1', 'nilai' => '18.125']],
            'klaim' => [['id' => 'klaim-beku', 'kegiatan_bersama' => true, 'sumber_klaim' => 'pengukuran',
                'kegiatan' => ['nama' => '=1+1', 'status' => 'terlaksana', 'anggaran' => '9007199254740993', 'uraian_pelaksanaan' => 'Narasi kegiatan resmi']]]]);
        $this->versi(['nilai' => '987654321', 'progres_kegiatan' => 'CANARY-DRAF'], false, 2);
        $this->pengukuran->indikator->update(['nama' => 'CANARY-MASTER']);
        RekomendasiPimpinan::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => 'Rekomendasi resmi', 'ditetapkan_oleh' => $this->actor->id, 'status' => 'disetujui',
            'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'superadmin', 'disetujui_pada' => now(), 'created_at' => now()]);
        $book = $this->ekspor();
        $this->assertSame(['Rekap', 'Kegiatan'], $book->getSheetNames());
        $rows = $this->isiSheet($book);
        $this->assertCount(2, $rows);
        $this->assertSame(['A', 'B'], array_column($rows, 'Kode Komponen'));
        $this->assertEquals(30.125, $rows[0]['Realisasi']);
        $this->assertEquals(20.25, $rows[0]['Target Periode']);
        $this->assertEquals(148.77, $rows[0]['Capaian (%)']);
        $this->assertSame('Indikator Uji', $rows[0]['Indikator']);
        $this->assertSame('Kendala resmi', $rows[0]['Kendala / Masalah']);
        $this->assertSame('Lengkap', $rows[0]['Status Bukti']);
        $this->assertSame('Rekomendasi resmi', $rows[0]['Rekomendasi Pimpinan']);
        $this->assertSame('Persetujuan cadangan', $rows[0]['Persetujuan Rekomendasi']);
        $headers = $book->getSheet(0)->rangeToArray('A8:AM8')[0];
        $column = array_search('Progres Kegiatan', $headers, true) + 1;
        $this->assertSame(DataType::TYPE_STRING, $book->getSheet(0)->getCell([$column, 9])->getDataType());
        $activities = $this->isiSheet($book, 'Kegiatan');
        $this->assertCount(1, $activities);
        $this->assertSame('=1+1', $activities[0]['Kegiatan']);
        $this->assertSame('9007199254740993', $activities[0]['Anggaran (Rp)']);
        $this->assertSame($rows[0]['Kunci Rekap'], $activities[0]['Kunci Rekap']);
        $audit = AuditLog::where('tindakan', 'laporan.ekspor')->sole();
        $this->assertSame($this->actor->id, $audit->actor_id);
        $this->assertSame($this->jadwal->id, $audit->objek_id);
        $this->assertSame(['jenis' => 'rekap', 'filter' => ['jadwal_id' => $this->jadwal->id, 'periode_id' => '']], $audit->nilai_baru);
        $this->assertNull($audit->dasar_izin);
        $this->assertStringNotContainsString('Narasi kegiatan resmi', $audit->toJson());
        $this->assertSame($version->id, $this->pengukuran->versions()->whereNotNull('disahkan_at')->sole()->id);
        $book->disconnectWorksheets();
    }

    public function test_ekspor_seluruh_filter_melewati_batas_halaman_dan_menghormati_deny_unit(): void
    {
        for ($index = 0; $index < 24; $index++) {
            $indicator = $this->pengukuran->indikator->replicate();
            $indicator->kode = 'I-EKSPOR-'.$index;
            $indicator->save();
            $context = $this->context->replicate();
            $context->indikator_id = $indicator->id;
            $context->save();
        }
        $allowed = Unit::create(['nama' => 'Unit diizinkan', 'created_by' => $this->actor->id]);
        $indicator = $this->pengukuran->indikator->replicate();
        $indicator->kode = 'I-DIIZINKAN';
        $indicator->unit_id = $allowed->id;
        $indicator->save();
        $context = $this->context->replicate();
        $context->indikator_id = $indicator->id;
        $context->unit_id = $allowed->id;
        $context->save();
        $book = $this->ekspor(['page' => 2]);
        $this->assertCount(26, $this->isiSheet($book));
        $book->disconnectWorksheets();
        $this->deny('laporan:ekspor', $this->unit->id);
        $book = $this->ekspor();
        $rows = $this->isiSheet($book);
        $this->assertCount(1, $rows);
        $this->assertSame('I-DIIZINKAN', $rows[0]['Kode Indikator']);
        $this->assertSame('Sebagian unit tidak dimuat sesuai hak akses Anda', $book->getSheet(0)->getCell('B5')->getValue());
        $book->disconnectWorksheets();
        $this->deny('laporan:read', $allowed->id);
        $this->getJson(str_replace('/rekap?', '/rekap/ekspor?', $this->url()))->assertUnprocessable();
    }

    public function test_pimpinan_dan_admin_dengan_grant_ekspor_sah_dan_deny_tetap_menang(): void
    {
        $this->versi(['klaim' => [['kegiatan' => ['nama' => 'CANARY-KEGIATAN-PRIVAT']]]]);
        $this->actor = $this->userWithRole('pimpinan');
        $book = $this->ekspor();
        $this->assertCount(1, $this->isiSheet($book));
        $book->disconnectWorksheets();
        $this->actor = $this->userWithRole('admin');
        DB::table('user_permission_granted')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'laporan:ekspor')->value('id'), 'unit_id' => null,
            'alasan' => 'Fixture grant ekspor', 'diberikan_oleh' => $this->actor->id, 'created_at' => now()]);
        $book = $this->ekspor();
        $this->assertSame([], $this->isiSheet($book, 'Kegiatan'));
        $this->assertSame('Detail kegiatan dibatasi sesuai akses unit', $this->isiSheet($book)[0]['Detail Kegiatan']);
        $book->disconnectWorksheets();
        $this->deny('laporan:ekspor');
        $this->getJson(str_replace('/rekap?', '/rekap/ekspor?', $this->url()))->assertForbidden();
    }

    #[DataProvider('penandaEkspor')]
    public function test_ekspor_mempertahankan_penanda_dan_nol_faktual(array $data, mixed $realisasi, mixed $target, mixed $persen, string $statusPerhitungan): void
    {
        $this->versi($data);
        $book = $this->ekspor();
        $row = $this->isiSheet($book)[0];
        $this->assertSame($realisasi, $row['Realisasi']);
        $this->assertSame($target, $row['Target Periode']);
        $this->assertSame($persen, $row['Capaian (%)']);
        $this->assertSame($statusPerhitungan, $row['Status Perhitungan']);
        $book->disconnectWorksheets();
    }

    public static function penandaEkspor(): array
    {
        return [
            'belum diukur didahulukan' => [['status_perhitungan' => 'belum_dapat_diukur', 'nilai' => null, 'target' => null, 'tanpa_target_periode' => true], 'Belum dapat diukur', 'Tanpa target periode', 'Belum dapat diukur', 'Belum dapat diukur'],
            'tanpa target dan nol' => [['nilai' => '0', 'target' => null, 'tanpa_target_periode' => true], 0, 'Tanpa target periode', 'Tanpa target periode', 'Terhitung'],
            'penyebut nol' => [['status_perhitungan' => 'tidak_dapat_dihitung', 'nilai' => null], 'Tidak dapat dihitung', 20, 'Tidak dapat dihitung', 'Tidak dapat dihitung'],
            'konteks tidak lengkap' => [['status_perhitungan' => null, 'nilai' => null], 'Konteks versi tidak lengkap', 20, 'Konteks versi tidak lengkap', 'Tidak tercatat pada versi ini'],
            'target nol' => [['nilai' => '0', 'target' => '0'], 0, 0, 'Tidak dihitung: target periode 0 atau negatif', 'Terhitung'],
            'target historis hilang' => [['target' => null], 30, 'Tidak tercatat pada versi ini', 'Tidak tercatat pada versi ini', 'Terhitung'],
            'realisasi historis hilang' => [['nilai' => null], 'Tidak tercatat pada versi ini', 20, 'Tidak tercatat pada versi ini', 'Terhitung'],
        ];
    }

    #[DataProvider('labelEkspor')]
    public function test_ekspor_memakai_label_dan_mempertahankan_kode_yang_belum_dikenal(string $tipe, string $peran, string $status, string $statusKegiatan, string $sumber, array $expected): void
    {
        $this->versi([
            'indikator' => [...$this->context->only(['nama', 'satuan', 'arah', 'presisi', 'desimal_tampilan']), 'tipe_perhitungan' => $tipe],
            'status_perhitungan' => $status,
            'komponen' => [['komponen_id' => (string) Str::uuid(), 'peran' => $peran]],
            'klaim' => [['sumber_klaim' => $sumber, 'kegiatan' => ['status' => $statusKegiatan]]],
        ]);
        $book = $this->ekspor();
        $row = $this->isiSheet($book)[0];
        $activity = $this->isiSheet($book, 'Kegiatan')[0];
        $this->assertSame($expected, [$row['Cara Hitung'], $row['Peran Komponen'], $row['Status Perhitungan'], $activity['Status Kegiatan'], $activity['Sumber Klaim']]);
        $book->disconnectWorksheets();
    }

    public static function labelEkspor(): array
    {
        return [
            'label pengguna' => ['manual', 'penjumlah', 'terhitung', 'tidak_terlaksana', 'rencana_aksi', ['Nilai manual', 'Penjumlah', 'Terhitung', 'Tidak terlaksana', 'Rencana Aksi']],
            'kode belum dikenal' => ['tipe_baru', 'peran_baru', 'status_baru', 'kegiatan_baru', 'sumber_baru', ['tipe_baru', 'peran_baru', 'status_baru', 'kegiatan_baru', 'sumber_baru']],
        ];
    }

    public function test_ekspor_tanpa_klaim_tidak_merujuk_sheet_kegiatan_kosong(): void
    {
        $this->versi(['klaim' => []]);
        $book = $this->ekspor();
        $this->assertSame('Tidak ada kegiatan tercatat', $this->isiSheet($book)[0]['Detail Kegiatan']);
        $this->assertSame([], $this->isiSheet($book, 'Kegiatan'));
        $book->disconnectWorksheets();
    }

    public function test_metadata_komponen_historis_yang_hilang_tidak_ditandai_tidak_berlaku(): void
    {
        $this->versi(['indikator' => ['tipe_perhitungan' => null], 'komponen' => [['komponen_id' => (string) Str::uuid(), 'nilai' => '30']]]);
        $book = $this->ekspor();
        $row = $this->isiSheet($book)[0];
        foreach (['Cara Hitung', 'Kode Komponen', 'Komponen', 'Peran Komponen', 'Bobot'] as $kolom) {
            $this->assertSame('Tidak tercatat pada versi ini', $row[$kolom]);
        }
        $book->disconnectWorksheets();
    }

    public function test_penanda_anggaran_terpisah_dan_kegagalan_pembentukan_tidak_diaudit(): void
    {
        $this->versi(['sasaran' => null, 'persyaratan_bukti' => null, 'klaim' => [
            ['kegiatan' => ['nama' => 'Key hilang']],
            ['kegiatan' => ['nama' => 'Nilai kosong', 'anggaran' => null]],
            ['kegiatan' => ['nama' => 'Nol faktual', 'anggaran' => '0']],
        ]]);
        $book = $this->ekspor();
        $this->assertSame('Tidak berlaku', $this->isiSheet($book)[0]['Kode Komponen']);
        $this->assertSame('Tidak tercatat pada versi ini', $this->isiSheet($book)[0]['Sasaran']);
        $this->assertSame('Tidak tercatat pada versi ini', $this->isiSheet($book)[0]['Histori Bukti Tidak Dapat Dipenuhi']);
        $this->assertSame(['Tidak tercatat pada versi ini', 'Belum diisi', 0], array_column($this->isiSheet($book, 'Kegiatan'), 'Anggaran (Rp)'));
        $book->disconnectWorksheets();
        $this->versi(['progres_kegiatan' => str_repeat('a', 32768)], nomor: 2);
        $this->getJson(str_replace('/rekap?', '/rekap/ekspor?', $this->url()))->assertUnprocessable()->assertJsonValidationErrors('ekspor');
        $this->assertSame(1, AuditLog::where('tindakan', 'laporan.ekspor')->count());
    }

    public function test_action_ekspor_memeriksa_ulang_izin_tanpa_form_request(): void
    {
        $this->deny('laporan:ekspor');
        $this->expectException(HttpException::class);
        app(EksporRekapIndikatorPeriode::class)->handle($this->actor, ['jadwal_id' => $this->jadwal->id]);
    }

    /** @return array<string, array{string, bool}> */
    public static function peranPenyetujuRekomendasi(): array
    {
        return [
            'pimpinan' => ['pimpinan', false],
            'perencanaan' => ['perencanaan', true],
            'superadmin' => ['superadmin', true],
        ];
    }

    #[DataProvider('peranPenyetujuRekomendasi')]
    public function test_rekomendasi_hanya_yang_disetujui_dan_berbeda_dari_rekomendasi_sebelumnya(string $peran, bool $cadangan): void
    {
        $this->versi(['rekomendasi_sebelumnya' => ['isi' => 'Rekomendasi sebelumnya beku']]);
        RekomendasiPimpinan::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => 'Rekomendasi berlaku', 'ditetapkan_oleh' => $this->actor->id, 'status' => 'disetujui', 'disetujui_oleh' => $this->actor->id,
            'disetujui_oleh_role' => $peran, 'disetujui_pada' => now(), 'created_at' => now()]);
        RekomendasiPimpinan::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $this->pengukuran->periode_id,
            'isi' => 'CANARY-REKOMENDASI-DRAF', 'ditetapkan_oleh' => $this->actor->id, 'status' => 'draf', 'created_at' => now()]);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.rekomendasi.isi', 'Rekomendasi berlaku')->where('baris.0.rekomendasi.cadangan', $cadangan)
            ->where('baris.0.rekomendasi_sebelumnya.isi', 'Rekomendasi sebelumnya beku'))
            ->assertDontSee('CANARY-REKOMENDASI-DRAF');
    }

    public function test_penanda_tidak_dapat_dihitung_menyertakan_alasan_resmi(): void
    {
        foreach ([false, true] as $index => $tanpaTarget) {
            $this->versi(['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung', 'alasan_tidak_dapat_dihitung' => 'Penyebut nol pada versi resmi',
                'target' => $tanpaTarget ? null : '20', 'tanpa_target_periode' => $tanpaTarget], nomor: $index + 1);
            $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
                ->where('baris.0.status_laporan', 'tidak_dapat_dihitung')->where('baris.0.nilai', null)
                ->where('baris.0.tanpa_target_periode', $tanpaTarget)
                // PRD §23 + Q64 §64.1 butir 5: periode bertarget yang tidak dapat dihitung tampil tersendiri,
                // sedangkan periode tanpa target tetap "Tidak dinilai" (Q47 §47.3 butir 4).
                ->where('baris.0.status_capaian', $tanpaTarget ? 'Tidak dinilai' : 'Tidak dapat dihitung')
                ->where('baris.0.alasan_tidak_dapat_dihitung', 'Penyebut nol pada versi resmi')
                ->where('baris.0.capaian_persen', null)->where('baris.0.selisih_target_pk', null));
        }
    }

    public function test_belum_dapat_diukur_mendahului_tanpa_target_periode(): void
    {
        $this->versi(['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur', 'target' => null, 'tanpa_target_periode' => true]);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.status_laporan', 'belum_dapat_diukur')->where('baris.0.capaian_persen', null)->where('baris.0.selisih_target_pk', null)
            // Q47 §47.3 butir 4: pada kondisi ini status capaian tidak dinilai, bukan "Belum ditetapkan".
            ->where('baris.0.status_capaian', 'Tidak dinilai'));
    }

    public function test_status_capaian_tercapai_pada_versi_resmi_tampil_dengan_waktu_penetapan(): void
    {
        // ISS-09.02 AC-1/AC-4, Q64 §64.1 butir 4: Tercapai hanya dibaca dari penetapan pada versi resmi baris ini.
        $versi = $this->versi(['nilai' => '80']);
        $this->tetapkanStatus($versi, StatusCapaian::STATUS_TERCAPAI);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.versi.id', $versi->id)
            ->where('baris.0.status_capaian', 'Tercapai')
            ->whereNot('baris.0.ditetapkan_pada', null));
    }

    public function test_revisi_status_capaian_memakai_baris_terbaru_yang_berlaku(): void
    {
        // ISS-09.02 AC-4: revisi menulis baris baru; yang berlaku adalah baris terbaru (Data Model §2.21).
        $versi = $this->versi(['nilai' => '80']);
        try {
            Carbon::setTestNow('2026-03-15 09:00:01.100000');
            $this->tetapkanStatus($versi, StatusCapaian::STATUS_TERCAPAI);
            Carbon::setTestNow('2026-03-15 09:00:02.100000');
            $this->tetapkanStatus($versi, StatusCapaian::STATUS_BELUM_TERCAPAI);

            $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
                ->where('baris.0.status_capaian', 'Belum tercapai')
                ->whereNot('baris.0.ditetapkan_pada', null));
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(2, StatusCapaian::where('pengukuran_versi_id', $versi->id)->count());
    }

    public function test_versi_koreksi_baru_mulai_belum_ditetapkan_dan_status_versi_lama_tetap_tersimpan(): void
    {
        // ISS-09.02 AC-3: versi koreksi baru tidak mewarisi status versi lama; riwayat versi lama tetap ada.
        $lama = $this->versi(['nilai' => '80']);
        $this->tetapkanStatus($lama, StatusCapaian::STATUS_TERCAPAI);
        $this->versi(['nilai' => '90'], nomor: 2);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 1)->where('baris.0.versi.nomor', 2)
            ->where('baris.0.status_capaian', 'Belum ditetapkan')
            ->where('baris.0.ditetapkan_pada', null));
        $this->assertSame(1, StatusCapaian::where('pengukuran_versi_id', $lama->id)->count());
    }

    public function test_tanpa_target_tetap_memuat_realisasi_dan_selisih_pk_tanpa_persen(): void
    {
        $this->versi(['nilai' => '0', 'target' => null, 'tanpa_target_periode' => true]);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('baris.0.status_laporan', 'tanpa_target_periode')->where('baris.0.nilai', '0')
            ->where('baris.0.selisih_target_pk', '-70')->where('baris.0.capaian_persen', null));
    }

    public function test_akses_laporan_fail_closed_dan_admin_tidak_otomatis_membaca_detail_kegiatan(): void
    {
        $this->versi(['klaim' => [['kegiatan' => ['nama' => 'CANARY-KEGIATAN', 'anggaran' => '5000000']]]]);
        $this->actingAs($this->userWithRole('pegawai'))->get($this->url())->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->where('auth.can.laporan_read', true)->where('baris.0.can.lihat_kegiatan', false)->where('baris.0.kegiatan', null))
            ->assertDontSee('CANARY-KEGIATAN')->assertDontSee('5000000');
        $this->deny('laporan:read');
        $this->actingAs($this->actor)->get($this->url())->assertForbidden();
        $this->actor->update(['status' => 'nonaktif']);
        $this->get($this->url())->assertRedirect(route('auth.pending'));
    }

    public function test_filter_tidak_sah_ditolak_dan_jadwal_kosong_aman(): void
    {
        $this->actingAs($this->actor)->getJson('/laporan/rekap?jadwal_id=invalid')->assertUnprocessable();
        $this->getJson($this->url(['periode_id' => (string) Str::uuid()]))->assertUnprocessable();
        $this->getJson($this->url(['page' => 0]))->assertUnprocessable();
        $this->get('/laporan/rekap?tahun=2025')->assertOk()->assertInertia(fn ($page) => $page->has('baris', 0));
    }

    public function test_deny_unit_diterapkan_sebelum_pagination_dan_perhitungan_total(): void
    {
        $restricted = Unit::create(['nama' => 'Unit dibatasi', 'created_by' => $this->actor->id]);
        for ($index = 0; $index < 21; $index++) {
            $indicator = $this->pengukuran->indikator->replicate();
            $indicator->kode = 'I-DENY-'.$index;
            $indicator->save();
            $context = $this->context->replicate();
            $context->indikator_id = $indicator->id;
            $context->unit_id = $restricted->id;
            $context->nama = 'A - indikator dibatasi '.$index;
            $context->save();
        }
        $this->deny('laporan:read', $restricted->id);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 1)->where('pagination.total', 1)
            ->where('baris.0.indikator.id', $this->pengukuran->indikator_id));
        $this->get($this->url(['page' => 2]))->assertOk()->assertInertia(fn ($page) => $page->has('baris', 0)->where('pagination.total', 1));
    }

    public function test_ra_resmi_periode_lama_tetap_tersedia_setelah_koreksi_konteks_periode_berikutnya(): void
    {
        $next = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $next->id,
            'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']);
        $newContext = JadwalSnapshot::create([...$this->context->only(['jadwal_id', 'indikator_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'periode_mulai_id' => $next->id]);
        RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'jadwal_snapshot_id' => $newContext->id, 'nomor' => 2,
            'snapshot' => ['target_periode' => [['periode_id' => $next->id, 'periode_nama' => 'Triwulan II', 'nilai' => '80', 'komponen' => []]]]]);

        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
            ->has('baris', 2)->where('baris.0.target_periode', '70')->where('baris.1.target_periode', '80'));
    }

    public function test_nama_periode_resmi_tidak_mengikuti_perubahan_master(): void
    {
        $this->versi();
        Periode::whereKey($this->pengukuran->periode_id)->update(['nama' => 'Nama periode sesudah koreksi']);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('baris.0.periode.nama', 'Triwulan I'));
    }

    public function test_target_nonpositif_memiliki_keterangan_server_dan_selisih_tetap_ada(): void
    {
        foreach (['0', '-10'] as $index => $target) {
            $this->versi(['target' => $target], nomor: $index + 1);
            $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page
                ->where('baris.0.target_periode', $target)->where('baris.0.capaian_persen', null)
                ->where('baris.0.keterangan_capaian', 'Tidak dihitung: target periode 0 atau negatif')
                ->where('baris.0.selisih_target_pk', '-40'));
        }
    }
}
