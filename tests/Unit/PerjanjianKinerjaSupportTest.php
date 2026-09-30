<?php

namespace Tests\Unit;

use App\Services\PerjanjianKinerja\PerjanjianKinerjaSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerjanjianKinerjaSupportTest extends TestCase
{
    public function test_sanitize_alasan_handles_nul_and_illegal_control_characters(): void
    {
        $dirty = "Alasan valid\0dengan byte NUL\x01\x02\x08 dan tab\t serta newline\nbaris kedua";
        $clean = PerjanjianKinerjaSupport::sanitizeAlasan($dirty);

        $this->assertStringNotContainsString("\0", $clean);
        $this->assertStringNotContainsString("\x01", $clean);
        $this->assertStringContainsString("\t", $clean);
        $this->assertStringContainsString("\n", $clean);
        $this->assertSame("Alasan validdengan byte NUL dan tab\t serta newline\nbaris kedua", $clean);
    }

    public function test_sanitize_alasan_truncates_to_1000_chars(): void
    {
        $long = str_repeat('X', 1500);
        $clean = PerjanjianKinerjaSupport::sanitizeAlasan($long);

        $this->assertSame(1000, mb_strlen($clean, 'UTF-8'));
    }

    public function test_bound_denied_metadata_filters_malformed_and_bounds_fields(): void
    {
        $validUuid = (string) Str::uuid();
        $input = [
            'renstra_id' => $validUuid,
            'tahun' => '2026',
            'nomor_pk' => '  PK/'.str_repeat('A', 300)."\0B  ",
            'tanggal_pk' => '2026-03-15',
            'alasan_penolakan' => 'pk_create_denied',
            'malicious_payload' => '<script>alert(1)</script>',
            'nested_object' => ['key' => 'value'],
        ];

        $bounded = PerjanjianKinerjaSupport::boundDeniedMetadata($input);

        $this->assertSame($validUuid, $bounded['renstra_id']);
        $this->assertSame(2026, $bounded['tahun']);
        $this->assertLessThanOrEqual(255, mb_strlen($bounded['nomor_pk']));
        $this->assertStringNotContainsString("\0", $bounded['nomor_pk']);
        $this->assertSame('2026-03-15', $bounded['tanggal_pk']);
        $this->assertSame('pk_create_denied', $bounded['alasan_penolakan']);
        $this->assertArrayNotHasKey('malicious_payload', $bounded);
        $this->assertArrayNotHasKey('nested_object', $bounded);
    }

    public function test_bound_denied_metadata_ignores_invalid_types_and_formats(): void
    {
        $input = [
            'renstra_id' => 'not-a-valid-uuid',
            'tahun' => 99999, // out of range 1900-2100
            'nomor_pk' => '',
            'tanggal_pk' => 'not-a-date',
            'alasan_penolakan' => str_repeat('R', 200),
        ];

        $bounded = PerjanjianKinerjaSupport::boundDeniedMetadata($input);

        $this->assertArrayNotHasKey('renstra_id', $bounded);
        $this->assertArrayNotHasKey('tahun', $bounded);
        $this->assertArrayNotHasKey('nomor_pk', $bounded);
        $this->assertArrayNotHasKey('tanggal_pk', $bounded);
        $this->assertLessThanOrEqual(100, mb_strlen($bounded['alasan_penolakan']));
    }

    public function test_validate_lampiran_items_validates_without_form_request_dependency(): void
    {
        $lampiran = [
            0 => ['mode' => 'file'],
            1 => ['mode' => 'file'],
        ];
        $fileValid = UploadedFile::fake()->create('valid.pdf', 100);
        $fileLongName = UploadedFile::fake()->create(str_repeat('a', 256).'.pdf', 100);

        $files = [
            0 => $fileValid,
            1 => $fileLongName,
        ];

        $validator = Validator::make([], []);
        PerjanjianKinerjaSupport::validateLampiranItems($lampiran, $files, $validator);

        $this->assertFalse($validator->errors()->has('lampiran.0.file'));
        $this->assertTrue($validator->errors()->has('lampiran.1.file'));
    }
}
