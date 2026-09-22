<?php

namespace Tests\Unit;

use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class JenisBerkasRequestValidationTest extends TestCase
{
    public function test_store_validation_fails_when_no_mode_is_selected(): void
    {
        $request = new StoreJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'pengukuran',
            'izinkan_file' => false,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
        ], $request->rules());

        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('modes', $validator->errors()->messages());
    }

    public function test_update_validation_fails_without_alasan(): void
    {
        $request = new UpdateJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Baru',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'alasan' => '',
            'expected_updated_at' => now()->toISOString(),
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('alasan', $validator->errors()->messages());
    }

    public function test_update_validation_fails_without_expected_updated_at(): void
    {
        $request = new UpdateJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Baru',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'izinkan_tautan' => false,
            'izinkan_teks' => false,
            'alasan' => 'Pembaruan alasan audit yang valid',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expected_updated_at', $validator->errors()->messages());
    }

    public function test_store_request_normalizes_null_urutan_and_format_diizinkan(): void
    {
        $request = StoreJenisBerkasRequest::create('/jenis-berkas', 'POST', [
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'pengukuran',
            'urutan' => null,
            'format_diizinkan' => ' PDF, docx,  xlsx ',
            'izinkan_file' => true,
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(0, $request->input('urutan'));
        $this->assertSame('pdf,docx,xlsx', $request->input('format_diizinkan'));
    }

    public function test_update_request_normalizes_null_urutan_and_format_diizinkan(): void
    {
        $request = UpdateJenisBerkasRequest::create('/jenis-berkas/123', 'PUT', [
            'nama' => 'Laporan Baru',
            'tahap' => 'pengukuran',
            'urutan' => '',
            'format_diizinkan' => 'JPG, JPEG, PNG',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan berkas',
            'expected_updated_at' => now()->toISOString(),
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame(0, $request->input('urutan'));
        $this->assertSame('jpg,jpeg,png', $request->input('format_diizinkan'));
    }
}
