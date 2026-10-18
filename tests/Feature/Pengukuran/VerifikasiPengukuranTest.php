<?php

namespace Tests\Feature\Pengukuran;

use App\Models\AuditLog;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Permission;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class VerifikasiPengukuranTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    /** Snapshot sintetis untuk menguji pembaca review tanpa menjalankan gerbang pengajuan yang diuji terpisah. */
    private function siapkanPerbandingan(array $lama = [], array $baru = [], bool $sah = true): void
    {
        $dasar = ['nilai' => '80.000000000000', 'status_perhitungan' => 'terhitung', 'sumber_nilai' => 'manual',
            'catatan' => null, 'alasan_tidak_dapat_dihitung' => null, 'progres_kegiatan' => 'Progres tetap.',
            'kendala_masalah' => 'Kendala tetap.', 'strategi_tindak_lanjut' => 'Strategi tetap.', 'tindak_lanjut_rekomendasi' => null,
            'indikator' => [...$this->context->only(['nama', 'definisi', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan']),
                'id' => $this->pengukuran->indikator_id, 'kode' => 'I-UJI'],
            'unit_kerja' => $this->unit->only(['id', 'nama']), 'pic' => $this->actor->only(['id', 'nama']),
            'target' => 70, 'target_pk' => 70, 'tanpa_target_periode' => false, 'rekomendasi_sebelumnya' => null,
            'komponen' => [], 'klaim' => [], 'persyaratan_bukti' => [], 'bukti_dukungs' => []];
        $lama = array_replace_recursive($dasar, $lama);
        $this->pengukuran->update(['nilai' => $lama['nilai'], 'status_perhitungan' => $lama['status_perhitungan'],
            'status_alur' => $sah ? 'disahkan' : 'diajukan', 'versi' => 4]);
        $this->versi($this->pengukuran, $lama, $sah);
        $periode = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $periode->id,
            'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-06-30', 'reviu_mulai' => '2026-06-30', 'reviu_selesai' => '2026-07-15']);
        $baru = array_replace_recursive($dasar, $baru);
        $this->pengukuran = PengukuranKinerja::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026,
            'periode_id' => $periode->id, 'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual',
            'status_alur' => 'diajukan', 'versi' => 2, 'nilai' => $baru['nilai'], 'status_perhitungan' => $baru['status_perhitungan'], 'created_by' => $this->actor->id]);
        $this->versi($this->pengukuran, $baru, false);
        $this->travelTo(now()->setDate(2026, 6, 30));
    }

    private function versi(PengukuranKinerja $p, array $payload, bool $sah, int $nomor = 1): KinerjaSnapshot
    {
        return KinerjaSnapshot::create(['pengukuran_id' => $p->id, 'rencana_aksi_versi_id' => $this->planVersion->id,
            'jadwal_snapshot_id' => $this->context->id, 'nomor' => $nomor, 'diajukan_by' => $this->actor->id, 'diajukan_at' => now(),
            'jalur_pengajuan' => 'perencanaan', 'dasar_izin_pengajuan' => ['fixture' => 'sintetis'],
            'disahkan_by' => $sah ? $this->actor->id : null, 'disahkan_at' => $sah ? now() : null,
            'snapshot' => [...$payload, 'periode' => $p->periode->only(['id', 'nama', 'urutan', 'is_nilai_akhir'])]]);
    }

    public static function perbandingan(): array
    {
        return [
            'nilai dan tiga narasi sama' => [[], [], true, true],
            'representasi desimal setara' => [['nilai' => '80'], ['nilai' => '80.000'], true, true],
            'angka berbeda dengan tampilan sama' => [['nilai' => '80.001'], ['nilai' => '80.002'], false, true],
            'nol adalah angka sah' => [['nilai' => '0'], ['nilai' => '0.00'], true, true],
            'null bukan nol' => [['nilai' => '0'], ['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur'], false, true],
            'dua null bukan realisasi identik' => [['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur'],
                ['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur'], false, true],
            'narasi sebagian sama' => [[], ['progres_kegiatan' => 'Progres berubah.'], true, false],
            'narasi kosong bukan identik' => [['progres_kegiatan' => null], ['progres_kegiatan' => null], true, false],
            'narasi spasi bukan identik' => [['kendala_masalah' => '   '], ['kendala_masalah' => '   '], true, false],
            'catatan tambahan bukan pemicu' => [['catatan' => 'A'], ['catatan' => 'B', 'tindak_lanjut_rekomendasi' => 'Baru'], true, true],
            'satuan berbeda' => [[], ['indikator' => ['satuan' => 'orang']], false, true],
            'definisi berbeda' => [[], ['indikator' => ['definisi' => 'Definisi baru.']], false, true],
            'presisi perhitungan berbeda' => [[], ['indikator' => ['presisi' => 4]], false, true],
            'label dan tampilan bukan rumus' => [[], ['indikator' => ['nama' => 'Label baru', 'desimal_tampilan' => 4]], true, true],
            'bobot komponen berbeda' => [['komponen' => [['komponen_id' => 'uji', 'peran' => 'penjumlah', 'bobot' => '1', 'nilai' => '80']]],
                ['komponen' => [['komponen_id' => 'uji', 'peran' => 'penjumlah', 'bobot' => '2', 'nilai' => '80']]], false, true],
        ];
    }

    #[DataProvider('perbandingan')]
    public function test_peringatan_memakai_snapshot_dan_tidak_menulis_data(array $lama, array $baru, bool $angka, bool $narasi): void
    {
        $this->siapkanPerbandingan($lama, $baru);
        $beku = $this->pengukuran->latestVersion->getRawOriginal('snapshot');
        $auditCount = AuditLog::count();
        $this->pengukuran->update(['nilai' => '999', 'status_perhitungan' => 'terhitung', 'progres_kegiatan' => 'Header hidup berubah.']);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.peringatan_verifikasi.periode_pembanding', 'Triwulan I 2026')
            ->where('pengukuran.peringatan_verifikasi.realisasi_sama', $angka)
            ->where('pengukuran.peringatan_verifikasi.narasi_sama', $narasi));
        $this->assertSame($beku, $this->pengukuran->latestVersion->fresh()->getRawOriginal('snapshot'));
        $this->assertSame($auditCount, AuditLog::count());
    }

    public function test_tidak_memakai_pengajuan_belum_sah_atau_melompati_periode_kosong(): void
    {
        $this->siapkanPerbandingan(sah: false);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.peringatan_verifikasi.periode_pembanding', null)
            ->where('pengukuran.peringatan_verifikasi.realisasi_sama', false)
            ->where('pengukuran.peringatan_verifikasi.narasi_sama', false));
        // Nomor urutan tidak harus rapat; periode tanpa pengukuran tidak boleh dilewati.
        $this->pengukuran->periode->update(['urutan' => 4]);
        $kosong = Periode::create(['nama' => 'Periode kosong', 'urutan' => 3, 'aktif' => true]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $kosong->id,
            'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-06-30', 'reviu_mulai' => '2026-06-30', 'reviu_selesai' => '2026-07-15']);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page->where('pengukuran.peringatan_verifikasi.periode_pembanding', null));
    }

    #[DataProvider('asalPengembalian')]
    public function test_peringatan_tidak_memblokir_review_dan_snapshot_tetap_utuh(string $status): void
    {
        $this->siapkanPerbandingan();
        $versi = $this->pengukuran->latestVersion;
        $beku = $versi->getRawOriginal('snapshot');
        $this->actingAs($this->actor);
        if ($status === 'diverifikasi') {
            $this->post('/verifikasi/'.$this->pengukuran->id.'/verifikasi', ['versi' => 2])->assertSessionHasNoErrors();
            $this->assertSame('diverifikasi', $this->pengukuran->fresh()->status_alur);
            $this->assertSame($beku, $versi->fresh()->getRawOriginal('snapshot'));
        }
        $this->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan',
            ['versi' => $this->pengukuran->fresh()->versi, 'catatan' => 'Perjelas alasan dan sumber data.'])->assertSessionHasNoErrors()->assertRedirect('/verifikasi');
        $this->assertSame('dikembalikan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame($beku, $versi->fresh()->getRawOriginal('snapshot'));
        $this->assertSame(1, $this->pengukuran->versions()->count());
    }

    public static function asalPengembalian(): array
    {
        return [['diajukan'], ['diverifikasi']];
    }

    #[DataProvider('alasanTidakSah')]
    public function test_validasi_awal_pengembalian_diaudit_tanpa_mutasi(?string $catatan): void
    {
        $this->siapkanPerbandingan();
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan',
            ['versi' => 2, 'catatan' => $catatan])->assertSessionHasErrors('catatan');
        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
        $this->assertSame(2, $this->pengukuran->fresh()->versi);
        $this->assertSame(0, $this->pengukuran->riwayats()->count());
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame('kembalikan', $audit->nilai_baru['tindakan_diminta']);
        $this->assertNotEmpty($audit->dasar_izin);
    }

    public static function alasanTidakSah(): array
    {
        return [[null], ['   '], ["\x01\x02"]];
    }

    public function test_deny_diperiksa_sebelum_validasi_dan_diaudit_sekali(): void
    {
        $this->siapkanPerbandingan();
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:kembalikan')->value('id'), 'unit_id' => $this->unit->id,
            'alasan' => 'Penolakan sintetis', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', [])->assertForbidden();
        $audit = AuditLog::where('tindakan', 'pengukuran.ditolak')->sole();
        $this->assertSame('otorisasi', $audit->nilai_baru['jenis_penolakan']);
        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
    }

    public function test_belum_dapat_diukur_dibaca_dan_dikembalikan_dengan_alasan(): void
    {
        $this->siapkanPerbandingan(baru: ['nilai' => null, 'status_perhitungan' => 'belum_dapat_diukur']);
        $snapshot = $this->pengukuran->latestVersion->getRawOriginal('snapshot');
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.status_perhitungan', 'belum_dapat_diukur')
            ->where('pengukuran.nilai', null)->where('pengukuran.progres_kegiatan', 'Progres tetap.'));
        $this->post('/verifikasi/'.$this->pengukuran->id.'/kembalikan', ['versi' => 2, 'catatan' => 'Alasan nilai belum tersedia perlu diperjelas.'])->assertSessionHasNoErrors();
        $this->assertSame($snapshot, $this->pengukuran->latestVersion->fresh()->getRawOriginal('snapshot'));
    }

    public function test_pembanding_tetap_versi_sah_walau_ada_pengajuan_lebih_baru(): void
    {
        $this->siapkanPerbandingan();
        $lama = PengukuranKinerja::where('id', '!=', $this->pengukuran->id)->sole();
        $sah = $lama->ratifiedVersion;
        $payload = [...$sah->snapshot, 'nilai' => '999', 'progres_kegiatan' => 'Versi terbaru belum sah.'];
        $this->versi($lama, $payload, false, 2);
        $lama->update(['status_alur' => 'diajukan']);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.peringatan_verifikasi.realisasi_sama', true)
            ->where('pengukuran.peringatan_verifikasi.narasi_sama', true));
        $this->assertSame($sah->getRawOriginal('snapshot'), $sah->fresh()->getRawOriginal('snapshot'));
    }

    public function test_snapshot_tanpa_metadata_tidak_memakai_header_atau_master_sebagai_fallback(): void
    {
        $this->siapkanPerbandingan();
        $payload = $this->pengukuran->latestVersion->snapshot;
        unset($payload['indikator']['presisi'], $payload['progres_kegiatan']);
        $this->versi($this->pengukuran, $payload, false, 2);
        $this->pengukuran->update(['progres_kegiatan' => 'Progres tetap.']);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.peringatan_verifikasi.realisasi_sama', false)
            ->where('pengukuran.peringatan_verifikasi.narasi_sama', false)
            ->where('pengukuran.progres_kegiatan', null));
    }

    public function test_pembanding_dengan_deny_baca_tidak_membocorkan_kesamaan_atau_label(): void
    {
        $this->siapkanPerbandingan();
        $unitLama = $this->unit;
        $unitBaru = Unit::create(['nama' => 'Unit baru', 'created_by' => $this->actor->id]);
        $context = $this->context->replicate();
        $context->fill(['nomor_versi' => 2, 'menggantikan_id' => $this->context->id, 'unit_id' => $unitBaru->id,
            'periode_mulai_id' => $this->pengukuran->periode_id, 'alasan_koreksi' => 'Perpindahan unit sintetis.', 'rujukan_koreksi' => 'Fixture QA']);
        $context->save();
        $payload = $this->pengukuran->latestVersion->snapshot;
        $payload['unit_kerja'] = $unitBaru->only(['id', 'nama']);
        $this->context = $context;
        $this->pengukuran->update(['jadwal_snapshot_id' => $context->id]);
        $this->versi($this->pengukuran, $payload, false, 2);
        DB::table('user_permission_denied')->insert(['id' => (string) Str::uuid(), 'user_id' => $this->actor->id,
            'permission_id' => Permission::where('kode', 'pengukuran:read')->value('id'), 'unit_id' => $unitLama->id,
            'alasan' => 'Deny unit historis sintetis.', 'ditetapkan_oleh' => $this->actor->id, 'created_at' => now()]);
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn ($page) => $page
            ->where('pengukuran.peringatan_verifikasi.periode_pembanding', null)
            ->where('pengukuran.peringatan_verifikasi.realisasi_sama', false)
            ->where('pengukuran.peringatan_verifikasi.narasi_sama', false));
    }

    public function test_justifikasi_kegiatan_ditunda_dibaca_dari_versi_pengajuan(): void
    {
        $klaim = ['id' => (string) Str::uuid(), 'komponen_id' => null, 'arah_dampak' => null, 'catatan' => null,
            'sumber_klaim' => 'rencana_aksi', 'kegiatan' => ['id' => (string) Str::uuid(), 'nama' => 'Kegiatan ditunda sintetis',
                'status' => 'ditunda', 'tujuan' => 'Pengujian catatan resmi', 'justifikasi' => 'Ruangan belum tersedia.',
                'tanggal_rencana' => null, 'tanggal_realisasi' => null, 'sasaran_peserta' => null, 'realisasi_peserta' => null,
                'uraian_pelaksanaan' => null, 'kendala' => null, 'strategi_tindaklanjut' => null, 'bukti_dukungs' => []]];
        $this->siapkanPerbandingan(baru: ['klaim' => [$klaim]]);
        $beku = $this->pengukuran->latestVersion->getRawOriginal('snapshot');
        $this->actingAs($this->actor)->get('/verifikasi/'.$this->pengukuran->id)->assertInertia(fn ($page) => $page
            ->where('pengukuran.klaim.0.kegiatan.status', 'ditunda')
            ->where('pengukuran.klaim.0.kegiatan.justifikasi', 'Ruangan belum tersedia.'));
        $this->assertSame($beku, $this->pengukuran->latestVersion->fresh()->getRawOriginal('snapshot'));
    }
}
