<?php

namespace Tests\Unit;

use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('auditReasons')]
    public function test_writer_safely_preserves_audit_reason_text(string $reason, string $expected): void
    {
        $user = User::factory()->create();
        $log = app(WriteAuditLog::class)->handle([
            'actor_type' => 'user', 'actor_id' => $user->id, 'sumber' => 'manual',
            'tindakan' => 'jenis_berkas.ubah', 'objek_tipe' => 'jenis_berkas',
            'objek_id' => (string) Str::uuid(), 'alasan' => $reason,
        ]);

        $this->assertSame($expected, $log->fresh()->alasan);
    }

    public static function auditReasons(): array
    {
        return [
            'NUL tidak memotong teks berikutnya' => ["Koreksi\0 lanjutan", 'Koreksi lanjutan'],
            'UTF-8 rusak' => ["Koreksi\xFF lanjutan", 'Koreksi? lanjutan'],
            'kontrol tidak tercetak' => ["Koreksi\x01\x7F lanjutan", 'Koreksi lanjutan'],
            'teks sah panjang dan format tetap utuh' => ["  Rujukan\t\r\n".str_repeat('é', 2100).'  ', "  Rujukan\t\r\n".str_repeat('é', 2100).'  '],
        ];
    }

    #[DataProvider('invalidAuditAttributes')]
    public function test_writer_rejects_invalid_reason_or_provenance(array $invalid, string $exception = InvalidArgumentException::class): void
    {
        $user = User::factory()->create();
        $this->expectException($exception);

        app(WriteAuditLog::class)->handle(array_replace([
            'actor_type' => 'user', 'actor_id' => $user->id, 'sumber' => 'manual',
            'tindakan' => 'jenis_berkas.ubah', 'objek_tipe' => 'jenis_berkas',
            'objek_id' => (string) Str::uuid(), 'alasan' => 'Alasan yang sah',
        ], $invalid));
    }

    public static function invalidAuditAttributes(): array
    {
        return [
            'alasan kosong' => [['alasan' => '  ']],
            'alasan bukan string' => [['alasan' => []]],
            'alasan hanya kontrol' => [['alasan' => "\x01\x7F"], ValidationException::class],
            'provenance tidak sah' => [['sumber' => 'payload']],
        ];
    }

    public function test_audit_logger_records_event_successfully(): void
    {
        $user = User::factory()->create();
        $targetId = (string) Str::uuid();

        $log = app(AuditLogger::class)->catat(
            actor: $user,
            tindakan: 'jenis_berkas.buat',
            objekTipe: 'jenis_berkas',
            objekId: $targetId,
            nilaiLama: null,
            nilaiBaru: ['nama' => 'Bukti Laporan'],
            alasan: null
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_log', [
            'id' => $log->id,
            'actor_id' => $user->id,
            'tindakan' => 'jenis_berkas.buat',
            'objek_id' => $targetId,
        ]);
    }

    #[DataProvider('loggerReasons')]
    public function test_audit_logger_preserves_nonblank_reason_or_uses_fallback(string $action, ?string $reason, string $expected): void
    {
        $user = User::factory()->create();
        $log = app(AuditLogger::class)->catat(
            actor: $user,
            tindakan: $action,
            objekTipe: 'fixture_audit',
            objekId: (string) Str::uuid(),
            alasan: $reason,
        );

        $this->assertSame($expected, $log->fresh()->alasan);
    }

    public static function loggerReasons(): array
    {
        $fallback = 'Pencatatan audit untuk tindakan target_tahunan.simpan.';

        return [
            'nol adalah teks sah' => ['target_tahunan.simpan', '0', '0'],
            'nol sah pada tindakan sensitif' => ['jenis_berkas.ubah', '0', '0'],
            'teks tetap melalui sanitasi writer' => ['target_tahunan.simpan', "  Koreksi\0 lanjutan  ", '  Koreksi lanjutan  '],
            'null memakai fallback' => ['target_tahunan.simpan', null, $fallback],
            'string kosong memakai fallback' => ['target_tahunan.simpan', '', $fallback],
            'whitespace memakai fallback' => ['target_tahunan.simpan', " \t\r\n ", $fallback],
        ];
    }

    #[DataProvider('blankReasons')]
    public function test_audit_logger_throws_exception_if_sensitive_action_lacks_reason(?string $reason): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = User::factory()->create();

        app(AuditLogger::class)->catat(
            actor: $user,
            tindakan: 'jenis_berkas.ubah',
            objekTipe: 'jenis_berkas',
            objekId: (string) Str::uuid(),
            nilaiLama: ['nama' => 'Lama'],
            nilaiBaru: ['nama' => 'Baru'],
            alasan: $reason
        );
    }

    public static function blankReasons(): array
    {
        return ['null' => [null], 'kosong' => [''], 'whitespace' => [" \t\r\n "]];
    }
}
