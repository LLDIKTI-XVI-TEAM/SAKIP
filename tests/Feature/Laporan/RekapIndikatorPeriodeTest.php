<?php

namespace Tests\Feature\Laporan;

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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_sasaran_boleh_fallback_ke_pasangan_ra_resmi(): void
    {
        $ra = RencanaAksiVersi::create([...$this->planVersion->only(['rencana_aksi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'nomor' => 2, 'snapshot' => ['sasaran' => ['id' => 'sasaran-versi-ra', 'kode' => 'S-RA', 'deskripsi' => 'Sasaran versi RA']]]);
        $this->versi(['sasaran' => null], ra: $ra);
        $this->actingAs($this->actor)->get($this->url())->assertOk()->assertInertia(fn ($page) => $page->where('baris.0.sasaran.kode', 'S-RA'));
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
