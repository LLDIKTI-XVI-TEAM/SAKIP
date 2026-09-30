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

    public function test_audit_logger_throws_exception_if_sensitive_action_lacks_reason(): void
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
            alasan: null
        );
    }
}
