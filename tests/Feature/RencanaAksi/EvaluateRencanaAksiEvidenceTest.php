<?php

namespace Tests\Feature\RencanaAksi;

use App\Actions\RencanaAksi\EvaluateRencanaAksiEvidence;
use App\Models\Pengaturan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class EvaluateRencanaAksiEvidenceTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected EvaluateRencanaAksiEvidence $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        $this->evaluator = app(EvaluateRencanaAksiEvidence::class);
    }

    /**
     * AC-1 / TEST-1: Persyaratan terpenuhi saat salah satu mode valid terisi jika semua_mode_wajib = false.
     */
    public function test_evaluates_evidence_as_complete_when_any_allowed_mode_is_fulfilled(): void
    {
        $jb = $this->createJenisBerkas([
            'wajib' => true,
            'semua_mode_wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
        ]);

        $evaluationBefore = $this->evaluator->handle($this->rencanaAksi);
        $this->assertFalse($evaluationBefore[0]['pemenuhan']['terpenuhi']);
        $this->assertFalse($this->evaluator->summary($this->rencanaAksi)['lengkap']);

        $this->createBuktiDukung([
            'jenis_berkas_id' => $jb->id,
            'mode' => 'tautan',
            'tautan' => 'https://lldikti16.kemdikbud.go.id/dokumen',
        ]);

        $evaluationAfter = $this->evaluator->handle($this->rencanaAksi);
        $this->assertTrue($evaluationAfter[0]['pemenuhan']['terpenuhi']);
        $this->assertSame(['tautan'], $evaluationAfter[0]['pemenuhan']['mode_terpenuhi']);
        $this->assertTrue($this->evaluator->summary($this->rencanaAksi)['lengkap']);
    }

    /**
     * AC-3 / TEST-3: Given semua_mode_wajib = true, When sebagian mode belum terpenuhi, Then persyaratan tetap belum lengkap.
     */
    public function test_evaluates_evidence_as_incomplete_when_semua_mode_wajib_is_true_and_some_modes_missing(): void
    {
        $jb = $this->createJenisBerkas([
            'wajib' => true,
            'semua_mode_wajib' => true,
            'izinkan_file' => false,
            'izinkan_tautan' => true,
            'izinkan_teks' => true,
        ]);

        // Baru mengisi tautan saja, teks belum
        $this->createBuktiDukung([
            'jenis_berkas_id' => $jb->id,
            'mode' => 'tautan',
            'tautan' => 'https://lldikti16.kemdikbud.go.id/kak',
        ]);

        $evaluation = $this->evaluator->handle($this->rencanaAksi);
        $this->assertFalse($evaluation[0]['pemenuhan']['terpenuhi']);
        $this->assertSame(['tautan'], $evaluation[0]['pemenuhan']['mode_terpenuhi']);
        $this->assertSame(['teks'], $evaluation[0]['pemenuhan']['mode_kurang']);
        $this->assertFalse($this->evaluator->summary($this->rencanaAksi)['lengkap']);

        // Melengkapi mode teks
        $this->createBuktiDukung([
            'jenis_berkas_id' => $jb->id,
            'mode' => 'teks',
            'isi_teks' => 'Keterangan lengkap implementasi rencana aksi.',
        ]);

        $evaluationComplete = $this->evaluator->handle($this->rencanaAksi);
        $this->assertTrue($evaluationComplete[0]['pemenuhan']['terpenuhi']);
        $this->assertEmpty($evaluationComplete[0]['pemenuhan']['mode_kurang']);
        $this->assertTrue($this->evaluator->summary($this->rencanaAksi)['lengkap']);
    }

    /**
     * §10.5 / Anti-macet: Given unggahan_aktif = false, When persyaratan file-only, Then ditandai tidak_dapat_dipenuhi dan lolos gerbang administratif.
     */
    public function test_evaluates_waiver_tidak_dapat_dipenuhi_when_file_uploads_disabled_globally(): void
    {
        Pengaturan::updateOrCreate(
            ['kunci' => 'berkas.unggahan_aktif'],
            ['grup' => 'berkas', 'nilai' => 'false', 'tipe' => 'boolean']
        );

        $this->createJenisBerkas([
            'wajib' => true,
            'semua_mode_wajib' => false,
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ]);

        $evaluation = $this->evaluator->handle($this->rencanaAksi);
        $this->assertTrue($evaluation[0]['pemenuhan']['terpenuhi']);
        $this->assertTrue($evaluation[0]['pemenuhan']['tidak_dapat_dipenuhi']);
        $this->assertSame(['file'], $evaluation[0]['pemenuhan']['mode_dikecualikan']);
        $this->assertNotNull($evaluation[0]['pemenuhan']['alasan_pengecualian']);
        $this->assertTrue($this->evaluator->summary($this->rencanaAksi)['lengkap']);
    }
}
