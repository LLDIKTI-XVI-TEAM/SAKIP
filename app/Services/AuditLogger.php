<?php

namespace App\Services;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /**
     * Catat peristiwa mutasi data ke audit_log
     *
     * @param  array<string, mixed>|null  $nilaiLama
     * @param  array<string, mixed>|null  $nilaiBaru
     * @param  array<string, mixed>|null  $dasarIzin
     */
    public static function catat(
        string $tindakan,
        string $objekTipe,
        string|int $objekId,
        ?array $nilaiLama = null,
        ?array $nilaiBaru = null,
        ?string $alasan = null,
        ?array $dasarIzin = null,
        ?int $actorId = null
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actorId ?? Auth::id(),
            'waktu' => Carbon::now(),
            'tindakan' => $tindakan,
            'objek_tipe' => $objekTipe,
            'objek_id' => (string) $objekId,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'alasan' => $alasan,
            'dasar_izin' => $dasarIzin,
        ]);
    }
}
