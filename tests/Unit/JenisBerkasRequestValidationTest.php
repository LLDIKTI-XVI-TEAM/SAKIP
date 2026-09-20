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
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('alasan', $validator->errors()->messages());
    }
}
