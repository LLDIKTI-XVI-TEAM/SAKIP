<?php

namespace Tests\Feature;

use App\Actions\Audit\WriteAuditLog;
use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\AuditLog;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Services\Authorization\PermissionResolver;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Services\Regulasi\RegulasiAttachments;
use App\Services\Renstra\RenstraAttachments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesRencanaAksiFixture;
use Tests\TestCase;

class UploadConfigurationTest extends TestCase
{
    use CreatesRencanaAksiFixture, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRencanaAksiFixture();
        Storage::fake('local');
    }

    public function test_perencanaan_menyimpan_format_ukuran_tanpa_izin_pengaturan_dan_tetap_teraudit(): void
    {
        $actor = $this->createUserWithRole('perencanaan');
        $this->actingAs($actor)->get('/jenis-berkas')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('JenisBerkas/Index')->where('can.create', true)->where('can.update', true)
            ->where('auth.can.jenisBerkas', true)->where('auth.can.regulasi', true)
            ->where('batasUnggahan.ukuran_maks_kb', 10240)->where('batasUnggahan.batas_atas_kb', 102400)
            ->missing('unggahanAktif')->missing('can.pengaturan_update')->missing('auth.can.storagePolicy'));
        $this->actingAs($actor)->post('/jenis-berkas', ['nama' => 'Dokumen uji', 'tahap' => 'pengukuran', 'izinkan_file' => true,
            'format_diizinkan' => 'pdf', 'ukuran_maks_kb' => 20480])->assertSessionHasNoErrors();
        $jenis = JenisBerkas::where('nama', 'Dokumen uji')->sole();
        $this->assertSame('pdf', $jenis->format_diizinkan);
        $this->assertSame(20480, $jenis->ukuran_maks_kb);
        $this->put('/jenis-berkas/'.$jenis->id, ['nama' => $jenis->nama, 'tahap' => $jenis->tahap, 'izinkan_file' => true,
            'format_diizinkan' => 'pdf,xlsx', 'ukuran_maks_kb' => 30720, 'alasan' => 'Penyesuaian kebutuhan dokumen.',
            'expected_updated_at' => $jenis->updated_at->toISOString()])->assertSessionHasNoErrors();
        $this->assertSame(30720, $jenis->fresh()->ukuran_maks_kb);
        $audit = AuditLog::where('tindakan', 'jenis_berkas.ubah')->sole();
        $this->assertSame('jenis_berkas:update', $audit->dasar_izin['permission']);
        $this->assertSame(20480, $audit->nilai_lama['ukuran_maks_kb']);
        $this->assertSame(30720, $audit->nilai_baru['ukuran_maks_kb']);
    }

    public function test_batas_persyaratan_ditegakkan_saat_simpan_dan_unggah(): void
    {
        $this->actingAs($this->actor)->post('/jenis-berkas', ['nama' => 'Terlalu besar', 'tahap' => 'pengukuran',
            'izinkan_file' => true, 'ukuran_maks_kb' => 102401])->assertSessionHasErrors('ukuran_maks_kb');
        $requirement = $this->createJenisBerkas(['ukuran_maks_kb' => 200000, 'format_diizinkan' => 'pdf']);
        $this->post(route('rencana-aksi.bukti.store', $this->rencanaAksi->id), ['jenis_berkas_id' => $requirement->id,
            'mode' => 'file', 'file' => UploadedFile::fake()->create('besar.pdf', 102401, 'application/pdf')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('berkas', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_setting_lama_diabaikan_tanpa_waiver_dan_unggahan_memakai_konfigurasi(): void
    {
        foreach (['berkas.unggahan_aktif' => 'false', 'berkas.ukuran_maks_kb' => '1', 'berkas.format_diizinkan' => 'txt'] as $key => $value) {
            Pengaturan::create(['kunci' => $key, 'nilai' => $value, 'tipe' => 'string', 'grup' => 'berkas']);
        }
        config(['uploads.default_max_kb' => 200, 'uploads.allowed_extensions' => 'pdf']);
        $requirement = $this->createJenisBerkas(['izinkan_tautan' => false, 'izinkan_teks' => false, 'format_diizinkan' => null, 'ukuran_maks_kb' => null]);
        $evaluation = app(EvaluateEvidence::class)->untuk('rencana_aksi', $this->indikator->id, collect())[0];
        $this->assertFalse($evaluation['pemenuhan']['terpenuhi']);
        $this->assertSame([], $evaluation['pemenuhan']['mode_dikecualikan']);
        $this->assertSame(200, $evaluation['ukuran_maks_kb']);
        $this->assertSame('pdf', $evaluation['format_diizinkan']);
        $url = route('rencana-aksi.bukti.store', $this->rencanaAksi->id);
        $this->actingAs($this->actor)->post($url, ['jenis_berkas_id' => $requirement->id, 'mode' => 'file',
            'file' => UploadedFile::fake()->create('valid.pdf', 200, 'application/pdf')])->assertSessionHasNoErrors();
        $this->post($url, ['jenis_berkas_id' => $requirement->id, 'mode' => 'file',
            'file' => UploadedFile::fake()->create('besar.pdf', 201, 'application/pdf')])->assertSessionHasErrors('file');
        $this->post($url, ['jenis_berkas_id' => $requirement->id, 'mode' => 'file',
            'file' => UploadedFile::fake()->create('salah.txt', 100, 'text/plain')])->assertSessionHasErrors('file');
        $this->assertSame(1, $this->rencanaAksi->buktiDukungs()->count());
        $this->assertSame(0, AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->count());
        $this->assertSame('false', Pengaturan::where('kunci', 'berkas.unggahan_aktif')->value('nilai'));
    }

    public function test_histori_marker_tetap_tampil_di_jejak_audit_tanpa_mengubah_baris(): void
    {
        $audit = app(WriteAuditLog::class)->handle(['actor_type' => 'user', 'actor_id' => $this->actor->id,
            'sumber' => 'manual', 'tindakan' => 'berkas.tandai_tidak_dapat_dipenuhi', 'objek_tipe' => 'rencana_aksi',
            'objek_id' => $this->rencanaAksi->id, 'alasan' => 'Histori pengecualian unggahan.',
            'nilai_baru' => ['status_pemenuhan' => 'tidak_dapat_dipenuhi', 'mode' => ['file']]]);
        $before = $audit->fresh()->getRawOriginal();
        $this->actingAs($this->actor)->get('/audit/'.$audit->id)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Audit/Show')->where('audit.tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')
            ->where('audit.nilai_baru.status_pemenuhan', 'tidak_dapat_dipenuhi')->where('audit.nilai_baru.mode', ['file']));
        $this->assertSame($before, $audit->fresh()->getRawOriginal());
        $this->assertSame(1, AuditLog::where('tindakan', 'berkas.tandai_tidak_dapat_dipenuhi')->count());
    }

    public function test_service_tiga_dokumen_dasar_memeriksa_batas_konfigurasi_sebelum_menyimpan(): void
    {
        config(['uploads.default_max_kb' => 200, 'uploads.allowed_extensions' => 'pdf']);
        Pengaturan::create(['kunci' => 'berkas.unggahan_aktif', 'nilai' => 'false', 'tipe' => 'boolean', 'grup' => 'berkas']);
        $regulasi = Regulasi::create(['jenis' => 'permen', 'nomor' => 'UJI-KONFIGURASI', 'tahun' => 2026,
            'tentang' => 'Dokumen sintetis', 'aktif' => true, 'created_by' => $this->actor->id]);
        $renstra = Renstra::sole();
        $pk = RenstraPk::sole();
        $actor = $this->actor;
        $decision = app(PermissionResolver::class)->resolve($actor, 'berkas:upload');
        $paths = [];
        $stores = [
            fn (array $items) => app(RenstraAttachments::class)->simpanLampiran($renstra, $items, $actor, $decision, $paths),
            fn (array $items) => app(RegulasiAttachments::class)->simpanLampiran($regulasi, $items, $actor, $decision, $paths),
            fn (array $items) => app(PerjanjianKinerjaAttachmentService::class)->simpanLampiran($pk, $items, $actor, $paths, $decision),
        ];
        foreach ($stores as $store) {
            foreach ([['besar.pdf', 201, 'application/pdf'], ['nama.exe', 100, 'application/pdf'], ['isi.pdf', 100, 'text/plain']] as [$name, $size, $mime]) {
                try {
                    $store([['mode' => 'file', 'file' => UploadedFile::fake()->create($name, $size, $mime)]]);
                    $this->fail('Lampiran tidak valid harus ditolak: '.$name);
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('lampiran', $exception->errors());
                }
            }
            $store([['mode' => 'file', 'file' => UploadedFile::fake()->create('valid.pdf', 200, 'application/pdf')]]);
        }
        $this->assertDatabaseCount('berkas', 3);
        $this->assertCount(3, Storage::disk('local')->allFiles());
    }

    public function test_route_storage_dan_batas_teknis_lama_tidak_tersedia(): void
    {
        $this->actingAs($this->actor)->get('/pengaturan/storage')->assertNotFound();
        $this->put('/pengaturan/storage', [])->assertNotFound();
        $this->patch('/jenis-berkas/'.Str::uuid().'/batas-teknis', [])->assertNotFound();
    }
}
