<?php

namespace Tests\Feature\Rekomendasi;

use App\Models\AuditLog;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\KinerjaSnapshot;
use App\Models\PengukuranKinerja;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RenstraPk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesPengukuranFixture;
use Tests\TestCase;

class RekomendasiBerlakuTest extends TestCase
{
    use CreatesPengukuranFixture, RefreshDatabase;

    public function test_draf_tidak_tampil_dan_hanya_rekomendasi_disetujui_terbaru_yang_berlaku_pada_periode_berikutnya(): void
    {
        $tw2 = Periode::create(['nama' => 'Triwulan II', 'urutan' => 2, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $tw2->id, 'pengisian_mulai' => '2026-06-01', 'pengisian_selesai' => '2026-06-15']);
        $berikutnya = PengukuranKinerja::create(['indikator_id' => $this->pengukuran->indikator_id, 'tahun' => 2026, 'periode_id' => $tw2->id, 'jadwal_snapshot_id' => $this->context->id, 'sumber_nilai' => 'manual', 'created_by' => $this->actor->id]);
        $tw1 = $this->pengukuran->periode_id;

        $this->rekomendasi(2026, $tw1, 'Draf belum berlaku.');
        $this->assertRekomendasiSebelumnya($berikutnya, null);

        $this->rekomendasi(2026, $tw1, 'Arahan lama.', now()->subDay());
        $baru = $this->rekomendasi(2026, $tw1, 'Arahan terbaru.', now());
        $this->rekomendasi(2026, $tw2->id, 'Rekomendasi periode yang sama tidak dibawa.', now());
        $this->assertRekomendasiSebelumnya($berikutnya, ['id' => $baru, 'isi' => 'Arahan terbaru.', 'tahun' => 2026, 'periode_id' => $tw1]);
        $this->assertRekomendasiSebelumnya($this->pengukuran, null);
    }

    public function test_rekomendasi_periode_terakhir_tahun_sebelumnya_berlaku_pada_periode_pertama_jadwal_berikutnya(): void
    {
        $tw4 = $this->jadwalTahunLalu();

        $this->rekomendasi(2025, $this->pengukuran->periode_id, 'Rekomendasi TW I 2025 bukan periode sebelumnya.', now());
        $tw4Lalu = $this->rekomendasi(2025, $tw4->id, 'Rekomendasi TW IV 2025.', now()->subDay());

        $this->assertRekomendasiSebelumnya($this->pengukuran, ['id' => $tw4Lalu, 'isi' => 'Rekomendasi TW IV 2025.', 'tahun' => 2025, 'periode_id' => $tw4->id]);
    }

    public function test_versi_beku_menampilkan_rekomendasi_saat_diajukan_bukan_data_hidup(): void
    {
        $tw4 = $this->jadwalTahunLalu();
        $saatDiajukan = $this->rekomendasi(2025, $tw4->id, 'Rekomendasi saat diajukan.', now()->subDay());
        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $this->pengajuan($saatDiajukan))->assertSessionHasNoErrors();
        $this->rekomendasi(2025, $tw4->id, 'Revisi setelah diajukan.', now());

        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('pengukuran.rekomendasi_sebelumnya.id', $saatDiajukan)->where('pengukuran.rekomendasi_sebelumnya.isi', 'Rekomendasi saat diajukan.')
            ->where('pengukuran.tindak_lanjut_rekomendasi', 'Ditindaklanjuti.'));

        // Versi lama tanpa key rekomendasi tidak mewarisi rekomendasi hidup.
        $version = $this->pengukuran->fresh()->latestVersion;
        KinerjaSnapshot::create([...$version->only(['pengukuran_id', 'rencana_aksi_versi_id', 'jadwal_snapshot_id', 'diajukan_by', 'diajukan_at', 'jalur_pengajuan', 'dasar_izin_pengajuan']),
            'nomor' => 2, 'snapshot' => Arr::except($version->snapshot, ['rekomendasi_sebelumnya'])]);
        $this->get('/verifikasi/'.$this->pengukuran->id)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('pengukuran.rekomendasi_sebelumnya', null));
    }

    public function test_pengajuan_dari_tampilan_rekomendasi_yang_sudah_diganti_ditolak_409_tanpa_versi(): void
    {
        $tw4 = $this->jadwalTahunLalu();
        $dilihat = $this->rekomendasi(2025, $tw4->id, 'Rekomendasi A.', now()->subDay());
        $pengganti = $this->rekomendasi(2025, $tw4->id, 'Rekomendasi B.', now());

        foreach ([$dilihat, null] as $token) {
            $this->actingAs($this->actor)->postJson('/pengukuran/'.$this->pengukuran->id, $this->pengajuan($token))
                ->assertStatus(409)->assertJsonValidationErrors('rekomendasi_sebelumnya');
        }
        $this->assertSame(['draft', 1], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(2, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id, $this->pengajuan(strtoupper($pengganti)))->assertSessionHasNoErrors();
        $beku = $this->pengukuran->fresh()->latestVersion->snapshot['rekomendasi_sebelumnya'];
        $this->assertSame([$pengganti, 'Rekomendasi B.'], [$beku['id'], $beku['isi']]);
    }

    #[DataProvider('tindakLanjutKosong')]
    public function test_rekomendasi_disetujui_menolak_tindak_lanjut_kosong_dan_checklist_memakai_gerbang_yang_sama(bool $lintasTahun, ?string $tindakLanjut): void
    {
        [$tahun, $periodeId] = $this->periodeSebelumnya($lintasTahun);
        $rekomendasi = $this->rekomendasi($tahun, $periodeId, 'Lengkapi evaluasi.', now()->subDay());
        $this->pengukuran->update(['nilai' => 75, 'status_perhitungan' => 'terhitung']);
        $pesan = 'Tindak lanjut rekomendasi wajib diisi sebelum pengajuan.';

        $this->actingAs($this->actor)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pengukuran.prasyarat.siap', false)
                ->where('pengukuran.prasyarat.alasan', [$pesan]));
        $sebelum = $this->pengukuran->fresh()->getRawOriginal();
        $this->postJson('/pengukuran/'.$this->pengukuran->id, [...$this->pengajuan($rekomendasi), 'tindak_lanjut_rekomendasi' => $tindakLanjut])
            ->assertUnprocessable()->assertJsonValidationErrors('tindak_lanjut_rekomendasi');

        $this->assertSame($sebelum, $this->pengukuran->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertDatabaseCount('riwayat_pengukurans', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    public static function tindakLanjutKosong(): array
    {
        return ['periode sebelumnya' => [false, null], 'hanya spasi' => [false, '   '], 'lintas tahun' => [true, null]];
    }

    public function test_aktivasi_terlambat_setelah_2026_tetap_mewajibkan_narasi_dan_tindak_lanjut(): void
    {
        [, $periodeId] = $this->periodeSebelumnya(false);
        RenstraPk::whereKey($this->jadwal->renstra_pk_id)->update(['tahun' => 2027, 'tanggal_pk' => '2027-01-01']);
        $this->jadwal->update(['tahun' => 2027, 'penutupan' => '2027-12-31', 'activated_at' => '2027-03-15 16:00:00']);
        foreach ($this->jadwal->periode as $window) {
            $window->update(collect($window->only(['pengisian_mulai', 'pengisian_selesai']))
                ->map(fn ($tanggal) => $tanggal->setYear(2027)->toDateString())->all());
        }
        $this->plan->update(['tahun' => 2027]);
        $kosong = array_fill_keys(PengukuranKinerja::NARASI, null);
        $this->pengukuran->update(['tahun' => 2027, 'nilai' => 75, 'status_perhitungan' => 'terhitung', ...$kosong]);
        $this->travelTo(now()->setDate(2027, 3, 16)->setTime(9, 0));
        $rekomendasi = $this->rekomendasi(2027, $periodeId, 'Rekomendasi tahun berikutnya.', now()->subDay());

        $this->actingAs($this->actor)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pengukuran.prasyarat.siap', false)
                ->has('pengukuran.prasyarat.alasan', 4));
        $sebelum = $this->pengukuran->fresh()->getRawOriginal();
        $this->postJson('/pengukuran/'.$this->pengukuran->id, [...$this->pengajuan($rekomendasi), ...$kosong])
            ->assertUnprocessable()->assertJsonValidationErrors(PengukuranKinerja::NARASI);

        $this->assertSame($sebelum, $this->pengukuran->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertDatabaseCount('riwayat_pengukurans', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    #[DataProvider('pengecualianTindakLanjut')]
    public function test_tindak_lanjut_tidak_wajib_tanpa_rekomendasi_disetujui_atau_pada_periode_lampau(string $kondisi): void
    {
        [$tahun, $periodeId] = $this->periodeSebelumnya(true);
        $rekomendasi = $kondisi === 'tanpa rekomendasi' ? null
            : $this->rekomendasi($tahun, $periodeId, 'Evaluasi periode sebelumnya.', $kondisi === 'draf' ? null : now()->subDay());
        if ($kondisi === 'lampau') {
            $this->jadwal->update(['activated_at' => '2026-03-15 16:00:00']);
            $this->travelTo(now()->setDate(2026, 4, 1)->setTime(9, 0));
        }
        $this->pengukuran->update(['nilai' => 75, 'status_perhitungan' => 'terhitung']);
        $this->actingAs($this->actor)->get('/pengukuran/'.$this->pengukuran->id.'/edit')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pengukuran.prasyarat.siap', true));
        $this->post('/pengukuran/'.$this->pengukuran->id, [...$this->pengajuan($kondisi === 'draf' ? null : $rekomendasi),
            'tindak_lanjut_rekomendasi' => null])->assertSessionHasNoErrors();

        $this->assertSame('diajukan', $this->pengukuran->fresh()->status_alur);
        $this->assertNull($this->pengukuran->fresh()->latestVersion->snapshot['tindak_lanjut_rekomendasi']);
        $this->assertDatabaseCount('pengukuran_versi', 1);
    }

    public static function pengecualianTindakLanjut(): array
    {
        return ['tidak ada rekomendasi' => ['tanpa rekomendasi'], 'hanya draf' => ['draf'], 'periode lampau' => ['lampau']];
    }

    public function test_draf_tetap_boleh_menyimpan_tindak_lanjut_kosong_meski_rekomendasi_disetujui(): void
    {
        [$tahun, $periodeId] = $this->periodeSebelumnya(true);
        $rekomendasi = $this->rekomendasi($tahun, $periodeId, 'Lengkapi evaluasi.', now()->subDay());

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            [...$this->pengajuan($rekomendasi), 'action' => 'draft', 'tindak_lanjut_rekomendasi' => null])->assertSessionHasNoErrors();

        $this->assertSame(['draft', 2, null], [$this->pengukuran->fresh()->status_alur, $this->pengukuran->fresh()->versi,
            $this->pengukuran->fresh()->tindak_lanjut_rekomendasi]);
        $this->assertDatabaseCount('pengukuran_versi', 0);
    }

    public function test_penolakan_inertia_mempertahankan_alasan_tindak_lanjut_dan_tidak_mengubah_draf(): void
    {
        [$tahun, $periodeId] = $this->periodeSebelumnya(true);
        $rekomendasi = $this->rekomendasi($tahun, $periodeId, 'Lengkapi evaluasi.', now()->subDay());
        $sebelum = $this->pengukuran->fresh()->getRawOriginal();

        $this->actingAs($this->actor)->post('/pengukuran/'.$this->pengukuran->id,
            [...$this->pengajuan($rekomendasi), 'tindak_lanjut_rekomendasi' => null])
            ->assertRedirect('/pengukuran/'.$this->pengukuran->id.'/edit')
            ->assertSessionHasErrors('tindak_lanjut_rekomendasi')
            ->assertSessionHas('hasil_pengajuan_terakhir.alasan', ['Tindak lanjut rekomendasi wajib diisi sebelum pengajuan.']);

        $this->assertSame($sebelum, $this->pengukuran->fresh()->getRawOriginal());
        $this->assertDatabaseCount('pengukuran_versi', 0);
        $this->assertSame(1, AuditLog::where('tindakan', 'pengukuran.ditolak')->count());
    }

    /** Periode sebelumnya sintetis untuk gerbang normal dan lintas tahun. */
    private function periodeSebelumnya(bool $lintasTahun): array
    {
        if ($lintasTahun) {
            return [2025, $this->jadwalTahunLalu()->id];
        }
        $this->pengukuran->periode->update(['nama' => 'Triwulan II', 'urutan' => 2]);
        $sebelumnya = Periode::create(['nama' => 'Triwulan I', 'urutan' => 1, 'aktif' => true, 'is_nilai_akhir' => false]);
        PeriodeJadwal::create(['jadwal_id' => $this->jadwal->id, 'periode_id' => $sebelumnya->id,
            'pengisian_mulai' => '2026-01-01', 'pengisian_selesai' => '2026-01-15']);

        return [2026, $sebelumnya->id];
    }

    /** @return array<string, mixed> */
    private function pengajuan(?string $rekomendasiDilihat): array
    {
        return ['versi' => 1, 'action' => 'ajukan', 'nilai' => 75, 'progres_kegiatan' => 'Progres.', 'kendala_masalah' => 'Kendala.',
            'strategi_tindak_lanjut' => 'Strategi.', 'tindak_lanjut_rekomendasi' => 'Ditindaklanjuti.', 'rekomendasi_sebelumnya_id' => $rekomendasiDilihat];
    }

    /** Jadwal 2025 ditutup dengan TW I dan TW IV yang juga mengukur indikator fixture. */
    private function jadwalTahunLalu(): Periode
    {
        $tw4 = Periode::create(['nama' => 'Triwulan IV', 'urutan' => 4, 'aktif' => true, 'is_nilai_akhir' => true]);
        $pk = RenstraPk::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2025, 'nomor_pk' => 'PK-2025', 'tanggal_pk' => '2025-01-01', 'created_by' => $this->actor->id]);
        $lalu = JadwalTahunan::create(['renstra_id' => $this->jadwal->renstra_id, 'tahun' => 2025, 'renstra_pk_id' => $pk->id, 'penutupan' => '2025-12-31', 'status' => 'ditutup', 'activated_at' => now()->subYear()]);
        foreach ([$this->pengukuran->periode_id => '2025-03-01', $tw4->id => '2025-12-01'] as $periodeId => $mulai) {
            PeriodeJadwal::create(['jadwal_id' => $lalu->id, 'periode_id' => $periodeId, 'pengisian_mulai' => $mulai, 'pengisian_selesai' => $mulai]);
        }
        JadwalSnapshot::create([...$this->context->only(['indikator_id', 'unit_id', 'nama', 'definisi', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan', 'target']),
            'jadwal_id' => $lalu->id, 'periode_mulai_id' => $this->pengukuran->periode_id]);

        return $tw4;
    }

    /** @param  array<string, mixed>|null  $expected */
    private function assertRekomendasiSebelumnya(PengukuranKinerja $pengukuran, ?array $expected): void
    {
        $this->actingAs($this->actor)->get('/pengukuran/'.$pengukuran->id.'/edit')->assertOk()->assertInertia(fn (AssertableInertia $page) => $expected === null
            ? $page->where('pengukuran.rekomendasi_sebelumnya', null)
            : $page->where('pengukuran.rekomendasi_sebelumnya', fn ($actual) => collect($expected)->every(fn ($value, $key) => $actual[$key] === $value) && is_string($actual['disetujui_pada'])));
    }

    /** Tanpa waktu persetujuan berarti draf. */
    private function rekomendasi(int $tahun, string $periodeId, string $isi, mixed $disetujuiPada = null): string
    {
        $id = (string) Str::uuid();
        DB::table('rekomendasi_pimpinan')->insert(['id' => $id, 'indikator_id' => $this->pengukuran->indikator_id, 'tahun' => $tahun, 'periode_id' => $periodeId, 'isi' => $isi,
            'ditetapkan_oleh' => $this->actor->id, 'created_at' => now(), ...($disetujuiPada === null ? [] : ['status' => 'disetujui', 'disetujui_oleh' => $this->actor->id, 'disetujui_oleh_role' => 'pimpinan', 'disetujui_pada' => $disetujuiPada])]);

        return $id;
    }
}
