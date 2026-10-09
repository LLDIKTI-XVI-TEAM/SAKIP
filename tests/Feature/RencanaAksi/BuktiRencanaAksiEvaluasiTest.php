<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

/**
 * Evaluator bukti bersama (Plan 13.4) dipakai untuk tahap `rencana_aksi`.
 */
class BuktiRencanaAksiEvaluasiTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected EvaluateEvidence $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->evaluator = app(EvaluateEvidence::class);
    }

    /** @return list<array<string, mixed>> */
    private function evaluasi(): array
    {
        return $this->evaluator->untuk('rencana_aksi', (string) $this->indikator->id, $this->rencanaAksi->buktiDukungs()->current()->get());
    }

    public function test_satu_mode_terisi_memenuhi_persyaratan_tanpa_semua_mode_wajib(): void
    {
        $jb = $this->createJenisBerkas(['semua_mode_wajib' => false]);

        $this->assertFalse($this->evaluasi()[0]['pemenuhan']['terpenuhi']);
        $this->assertFalse($this->evaluator->ringkasan($this->evaluasi())['lengkap']);

        $this->createBuktiDukung(['jenis_berkas_id' => $jb->id]);

        $hasil = $this->evaluasi();
        $this->assertTrue($hasil[0]['pemenuhan']['terpenuhi']);
        $this->assertSame(['tautan'], $hasil[0]['pemenuhan']['mode_terpenuhi']);
        $this->assertSame(['lengkap' => true, 'total_wajib' => 1, 'terpenuhi_wajib' => 1], $this->evaluator->ringkasan($hasil));
    }

    public function test_semua_mode_wajib_belum_lengkap_bila_sebagian_mode_kosong(): void
    {
        $jb = $this->createJenisBerkas(['semua_mode_wajib' => true, 'izinkan_file' => false]);
        $this->createBuktiDukung(['jenis_berkas_id' => $jb->id]);

        $hasil = $this->evaluasi();
        $this->assertFalse($hasil[0]['pemenuhan']['terpenuhi']);
        $this->assertSame(['teks'], $hasil[0]['pemenuhan']['mode_kurang']);

        $this->createBuktiDukung(['jenis_berkas_id' => $jb->id, 'mode' => 'teks', 'tautan' => null, 'isi_teks' => 'Keterangan lengkap.']);

        $hasil = $this->evaluasi();
        $this->assertTrue($hasil[0]['pemenuhan']['terpenuhi']);
        $this->assertSame([], $hasil[0]['pemenuhan']['mode_kurang']);
    }

    public function test_persyaratan_file_saja_ditandai_tidak_dapat_dipenuhi_saat_unggahan_nonaktif(): void
    {
        Pengaturan::updateOrCreate(['kunci' => 'berkas.unggahan_aktif'], ['grup' => 'berkas', 'nilai' => 'false', 'tipe' => 'boolean']);
        $this->createJenisBerkas(['izinkan_tautan' => false, 'izinkan_teks' => false]);

        $hasil = $this->evaluasi();
        $this->assertTrue($hasil[0]['pemenuhan']['terpenuhi']);
        $this->assertTrue($hasil[0]['pemenuhan']['tidak_dapat_dipenuhi']);
        $this->assertSame(['file'], $hasil[0]['pemenuhan']['mode_dikecualikan']);
        $this->assertTrue($this->evaluator->ringkasan($hasil)['lengkap']);
    }

    public function test_persyaratan_tahap_lain_dan_opsional_tidak_mempengaruhi_ringkasan(): void
    {
        $this->createJenisBerkas(['nama' => 'Bukti Pengukuran', 'tahap' => 'pengukuran']);
        $this->createJenisBerkas(['nama' => 'Lampiran Opsional', 'wajib' => false]);

        $hasil = $this->evaluasi();
        $this->assertCount(1, $hasil);
        $this->assertSame('Lampiran Opsional', $hasil[0]['nama']);
        $this->assertSame(['lengkap' => true, 'total_wajib' => 0, 'terpenuhi_wajib' => 0], $this->evaluator->ringkasan($hasil));
    }
}
