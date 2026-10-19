<?php

namespace Tests\Unit;

use App\Http\Requests\DeleteJenisBerkasRequest;
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

    public function test_store_request_does_not_cast_non_numeric_urutan_to_zero(): void
    {
        $request = StoreJenisBerkasRequest::create('/jenis-berkas', 'POST', [
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'pengukuran',
            'urutan' => 'abc',
            'izinkan_file' => true,
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('abc', $request->input('urutan'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('urutan', $validator->errors()->messages());
    }

    public function test_update_request_does_not_cast_non_numeric_urutan_to_zero(): void
    {
        $request = UpdateJenisBerkasRequest::create('/jenis-berkas/123', 'PUT', [
            'nama' => 'Laporan Baru',
            'tahap' => 'pengukuran',
            'urutan' => '12abc',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan berkas',
            'expected_updated_at' => now()->toISOString(),
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame('12abc', $request->input('urutan'));

        $validator = Validator::make($request->all(), $request->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('urutan', $validator->errors()->messages());
    }

    public function test_validation_rejects_integers_exceeding_32bit_limit(): void
    {
        $storeRequest = new StoreJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'pengukuran',
            'urutan' => 2147483648,
            'ukuran_maks_kb' => 2147483648,
            'izinkan_file' => true,
        ], $storeRequest->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('urutan', $validator->errors()->messages());
        $this->assertArrayHasKey('ukuran_maks_kb', $validator->errors()->messages());
    }

    public function test_update_validation_fails_with_invalid_date_expected_updated_at(): void
    {
        $request = new UpdateJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Baru',
            'tahap' => 'pengukuran',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan alasan audit yang valid',
            'expected_updated_at' => 'bukan-sebuah-tanggal-valid',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expected_updated_at', $validator->errors()->messages());
        $this->assertSame('Format timestamp versi tidak valid.', $validator->errors()->first('expected_updated_at'));
    }

    public function test_delete_validation_fails_with_invalid_date_expected_updated_at(): void
    {
        $request = new DeleteJenisBerkasRequest;
        $validator = Validator::make([
            'alasan' => 'Alasan penghapusan valid',
            'expected_updated_at' => 'format-tanggal-salah',
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expected_updated_at', $validator->errors()->messages());
        $this->assertSame('Format timestamp versi tidak valid.', $validator->errors()->first('expected_updated_at'));
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

    public function test_store_validation_rejects_unsupported_tahap(): void
    {
        $request = new StoreJenisBerkasRequest;
        $validator = Validator::make([
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'kegiatan',
            'izinkan_file' => true,
        ], $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('Tahap Kegiatan (SPJ) belum didukung karena gerbang buktinya belum diimplementasikan.', $validator->errors()->first('tahap'));

        $validatorRencanaAksi = Validator::make([
            'nama' => 'Laporan Akuntabilitas',
            'tahap' => 'rencana_aksi',
            'izinkan_file' => true,
        ], $request->rules(), $request->messages());

        $this->assertArrayNotHasKey('tahap', $validatorRencanaAksi->errors()->messages());
    }

    public function test_tahap_perencanaan_kegiatan_diterima_dan_penanda_beranggaran_hanya_untuk_tahap_itu(): void
    {
        $request = new StoreJenisBerkasRequest;
        $data = ['nama' => 'RAB', 'tahap' => 'kegiatan_perencanaan', 'izinkan_file' => true, 'hanya_kegiatan_beranggaran' => true];

        $validator = Validator::make($data, $request->rules(), $request->messages());
        $this->assertFalse($validator->fails());
        $this->assertTrue($validator->validated()['hanya_kegiatan_beranggaran']);

        $pengukuran = Validator::make(['tahap' => 'pengukuran'] + $data, $request->rules(), $request->messages());
        $this->assertFalse($pengukuran->fails());
        $this->assertArrayNotHasKey('hanya_kegiatan_beranggaran', $pengukuran->validated());
    }

    public function test_update_validation_rejects_unsupported_tahap(): void
    {
        $request = new UpdateJenisBerkasRequest;
        $data = [
            'nama' => 'Laporan Baru',
            'izinkan_file' => true,
            'alasan' => 'Pembaruan alasan audit yang valid',
            'expected_updated_at' => now()->toISOString(),
        ];
        $validator = Validator::make(['tahap' => 'kegiatan'] + $data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertSame('Tahap Kegiatan (SPJ) belum didukung karena gerbang buktinya belum diimplementasikan.', $validator->errors()->first('tahap'));

        $validatorRencanaAksi = Validator::make(['tahap' => 'rencana_aksi'] + $data, $request->rules(), $request->messages());
        $this->assertArrayNotHasKey('tahap', $validatorRencanaAksi->errors()->messages());
    }
}
