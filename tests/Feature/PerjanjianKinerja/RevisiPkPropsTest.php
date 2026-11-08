<?php

namespace Tests\Feature\PerjanjianKinerja;

use App\Actions\PerjanjianKinerja\SetujuiRevisiPerjanjianKinerja;
use App\Models\Berkas;
use App\Models\JadwalTahunan;
use App\Models\Renstra;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesRevisiPkFixture;
use Tests\TestCase;

/** Props halaman PK untuk linimasa versi dan form revisi; aturan pratinjau sama dengan Action revisi. */
class RevisiPkPropsTest extends TestCase
{
    use CreatesRevisiPkFixture;
    use RefreshDatabase;

    public function test_perencanaan_menerima_linimasa_versi_dan_data_form_revisi(): void
    {
        $awal = $this->lampiranInduk('PK awal');
        $versi2 = $this->revisi([$this->entri($this->iku1, '85')]);
        $sesudah = $this->lampiranInduk('Lampiran sesudah revisi');

        $props = $this->props($this->perencanaan);

        [$v1, $v2] = $props['versi'];
        $this->assertSame([1, [], [$awal->id]], [$v1['nomor_versi'], $v1['perubahan_target'], array_column($v1['berkas'], 'id')]);
        $this->assertSame([2, 'B-12/2026', '2026-11-01', ['id' => $this->tw1->periode_id, 'nama' => 'Triwulan I'], self::ALASAN, $this->perencanaan->nama],
            [$v2['nomor_versi'], $v2['nomor_surat'], $v2['tanggal_surat'], $v2['periode_mulai'], $v2['alasan'], $v2['disetujui_oleh']]);
        $this->assertSame([['indikator_id' => $this->iku1->indikator_id, 'kode' => 'IKU-1', 'lama' => '80', 'baru' => '85', 'desimal_tampilan' => 2]], $v2['perubahan_target']);
        $this->assertSame(Berkas::where('berkasable_id', $versi2->id)->pluck('id')->all(), array_column($v2['berkas'], 'id'));
        $this->assertArrayNotHasKey('berkas_awal_ids', $v1);
        // Lampiran induk sesudah revisi pertama tetap milik PK, tetapi bukan bagian PK awal.
        $this->assertEqualsCanonicalizing([$awal->id, $sesudah->id], array_column($props['pk']['berkas'], 'id'));

        $revisi = $props['revisi'];
        $this->assertSame([2, JadwalTahunan::tokenDasar($this->jadwal->snapshotDasar()), ['id' => $this->tw1->periode_id, 'nama' => 'Triwulan I'], true, null, []],
            [$revisi['expected_nomor_versi'], $revisi['expected_dasar'], $revisi['periode_mulai'], $revisi['dapat_ubah_target'],
                $revisi['alasan_tidak_dapat_ubah_target'], $revisi['pengukuran_belum_disahkan']]);
        $this->assertSame([
            ['indikator_id' => $this->iku1->indikator_id, 'kode' => 'IKU-1', 'nama' => 'Indikator IKU-1', 'satuan' => 'persen', 'nilai' => '85', 'desimal_tampilan' => 2],
            ['indikator_id' => $this->iku2->indikator_id, 'kode' => 'IKU-2', 'nama' => 'Indikator IKU-2', 'satuan' => 'persen', 'nilai' => '60', 'desimal_tampilan' => 2],
        ], $revisi['target']);
        $this->assertTrue($props['can']['revisi']);
    }

    public function test_pengukuran_belum_disahkan_dikirim_untuk_indikator_yang_periode_mulainya_dinaikkan(): void
    {
        $this->koreksiSnapshot($this->iku2, '60', $this->tw4);
        $this->sahkanSampai(2, $this->iku1, $this->iku2);
        $this->header($this->iku2, $this->tw3, 'draft');

        $revisi = $this->props($this->perencanaan)['revisi'];

        $this->assertSame(['id' => $this->tw3->periode_id, 'nama' => 'Triwulan III'], $revisi['periode_mulai']);
        $this->assertSame([$this->iku2->indikator_id => [['periode_id' => $this->tw3->periode_id, 'periode_nama' => 'Triwulan III', 'status_alur' => 'draft', 'nomor_versi' => 1]]],
            $revisi['pengukuran_belum_disahkan']);
    }

    public function test_renstra_nonaktif_menutup_ubah_target_tanpa_menutup_revisi_dokumen(): void
    {
        DB::table('renstras')->where('id', $this->pk->renstra_id)->update(['status' => Renstra::STATUS_NONAKTIF]);

        $props = $this->props($this->perencanaan);

        $this->assertSame([false, SetujuiRevisiPerjanjianKinerja::PESAN_RENSTRA], [$props['revisi']['dapat_ubah_target'], $props['revisi']['alasan_tidak_dapat_ubah_target']]);
        $this->assertTrue($props['can']['revisi']);
    }

    public function test_pembaca_tanpa_izin_revisi_tetap_melihat_linimasa(): void
    {
        $this->revisi();

        $props = $this->props($this->userWithRole('pegawai'));

        $this->assertNull($props['revisi']);
        $this->assertFalse($props['can']['revisi']);
        $this->assertSame([1, 2], array_column($props['versi'], 'nomor_versi'));
    }

    public function test_perubahan_target_dapat_berasal_dari_koreksi_snapshot_yang_diserap_revisi_dokumen(): void
    {
        $this->koreksiSnapshot($this->iku2, '65', $this->tw1);
        $this->revisi();

        $this->assertSame([['indikator_id' => $this->iku2->indikator_id, 'kode' => 'IKU-2', 'lama' => '60', 'baru' => '65', 'desimal_tampilan' => 2]],
            $this->props($this->perencanaan)['versi'][1]['perubahan_target']);
    }

    public function test_revisi_yang_mengembalikan_nilai_versi_sebelumnya_tanpa_perubahan_target(): void
    {
        $this->koreksiSnapshot($this->iku2, '65', $this->tw1);
        $this->revisi([$this->entri($this->iku2, '60')]);

        $this->assertSame([], $this->props($this->perencanaan)['versi'][1]['perubahan_target']);
    }

    public function test_indikator_tengah_tahun_muncul_tanpa_nilai_lama_di_versi_berikutnya(): void
    {
        $this->revisi();
        $tengah = $this->terjadwal('IKU-3', '50', mulai: $this->tw3);
        $this->revisi();

        $this->assertSame([['indikator_id' => $tengah->indikator_id, 'kode' => 'IKU-3', 'lama' => null, 'baru' => '50', 'desimal_tampilan' => 2]],
            $this->props($this->perencanaan)['versi'][2]['perubahan_target']);
    }

    /** @return array<string, mixed> */
    private function props(User $user): array
    {
        return $this->actingAs($user)->get("/perjanjian-kinerja/{$this->pk->id}")->assertOk()->viewData('page')['props'];
    }

    private function lampiranInduk(string $nama): Berkas
    {
        return Berkas::forceCreate(['id' => (string) Str::uuid(), 'berkasable_type' => 'renstra_pk', 'berkasable_id' => $this->pk->id,
            'mode' => 'tautan', 'tautan' => 'https://example.test/'.Str::slug($nama), 'nama_asli' => $nama, 'uploaded_by' => $this->actor->id]);
    }
}
