<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_records_event_successfully(): void
    {
        $user = User::factory()->create();

        $log = AuditLogger::catat(
            actor: $user,
            tindakan: 'jenis_berkas.buat',
            objekTipe: 'jenis_berkas',
            objekId: 'uuid-1234',
            nilaiLama: null,
            nilaiBaru: ['nama' => 'Bukti Laporan'],
            alasan: null
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'actor_id' => $user->id,
            'tindakan' => 'jenis_berkas.buat',
        ]);
    }

    public function test_audit_logger_throws_exception_if_sensitive_action_lacks_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = User::factory()->create();

        AuditLogger::catat(
            actor: $user,
            tindakan: 'jenis_berkas.ubah',
            objekTipe: 'jenis_berkas',
            objekId: 'uuid-1234',
            nilaiLama: ['nama' => 'Lama'],
            nilaiBaru: ['nama' => 'Baru'],
            alasan: null
        );
    }
}
