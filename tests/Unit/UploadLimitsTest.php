<?php

namespace Tests\Unit;

use App\Http\Requests\Regulasi\StoreRegulasiRequest;
use App\Http\Requests\Renstra\StoreRenstraRequest;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use App\Services\Storage\UploadLimits;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    public function test_default_dan_override_persyaratan_dibatasi_ceiling_konfigurasi(): void
    {
        $this->assertSame(['ukuran_maks_kb' => 10240, 'format_diizinkan' => 'pdf,docx,xlsx,jpg,jpeg,png'], UploadLimits::resolve());
        config(['uploads.default_max_kb' => 8192, 'uploads.max_kb' => 4096, 'uploads.allowed_extensions' => ' PDF, PNG, pdf ']);
        $this->assertSame(['ukuran_maks_kb' => 4096, 'format_diizinkan' => 'pdf,png'], UploadLimits::resolve());
        $this->assertSame(['ukuran_maks_kb' => 2048, 'format_diizinkan' => 'xlsx'], UploadLimits::resolve(2048, 'xlsx'));
        $this->assertSame(['ukuran_maks_kb' => 4096, 'format_diizinkan' => 'xlsx'], UploadLimits::resolve(null, 'xlsx'));
        $this->assertSame(['ukuran_maks_kb' => 2048, 'format_diizinkan' => 'pdf,png'], UploadLimits::resolve(2048));
        $this->assertSame(['ukuran_maks_kb' => 4096, 'format_diizinkan' => 'pdf'], UploadLimits::resolve(200000, 'pdf'));
    }

    public function test_ceiling_di_atas_keputusan_dipotong_dan_konfigurasi_rusak_tidak_membuka_batas(): void
    {
        config(['uploads.max_kb' => 200000, 'uploads.default_max_kb' => 150000]);
        $this->assertSame(102400, UploadLimits::maxKb());
        $this->assertSame(102400, UploadLimits::resolve()['ukuran_maks_kb']);
        config(['uploads.max_kb' => -1, 'uploads.default_max_kb' => 'bukan-angka', 'uploads.allowed_extensions' => '']);
        $this->assertSame(['ukuran_maks_kb' => 10240, 'format_diizinkan' => 'pdf,docx,xlsx,jpg,jpeg,png'], UploadLimits::resolve());
    }

    public function test_request_tiga_dokumen_dasar_memakai_konfigurasi_dan_memeriksa_isi_beserta_ekstensi(): void
    {
        config(['uploads.default_max_kb' => 200, 'uploads.allowed_extensions' => 'pdf']);
        $requestRules = [(new StoreRenstraRequest)->rules(), (new StoreRegulasiRequest)->rules(), PerjanjianKinerjaSupport::lampiranRules()];
        foreach ($requestRules as $rules) {
            $rules = array_filter($rules, fn (string $key): bool => str_starts_with($key, 'lampiran'), ARRAY_FILTER_USE_KEY);
            foreach ([['valid.pdf', 200, 'application/pdf', false], ['besar.pdf', 201, 'application/pdf', true],
                ['nama.exe', 100, 'application/pdf', true], ['isi.pdf', 100, 'text/plain', true]] as [$name, $size, $mime, $fails]) {
                $validator = Validator::make(['lampiran' => [['mode' => 'file', 'file' => UploadedFile::fake()->create($name, $size, $mime)]]], $rules);
                $this->assertSame($fails, $validator->fails(), $name);
                if ($fails) {
                    $this->assertStringNotContainsString('validation.', $validator->errors()->first('lampiran.0.file'));
                }
            }
        }
    }
}
