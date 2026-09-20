<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;

class AuditLogger
{
    protected const SENSITIVE_ACTIONS = [
        'jenis_berkas.ubah',
        'jenis_berkas.hapus',
    ];

    public static function catat(
        User $actor,
        string $tindakan,
        string $objekTipe,
        string $objekId,
        ?array $nilaiLama = null,
        ?array $nilaiBaru = null,
        ?string $alasan = null,
        ?array $dasarIzin = null
    ): AuditLog {
        if (in_array($tindakan, self::SENSITIVE_ACTIONS, true)) {
            if (empty($alasan) || trim($alasan) === '') {
                throw new InvalidArgumentException("Tindakan sensitif {$tindakan} wajib menyertakan alasan.");
            }
        }

        return AuditLog::create([
            'actor_id' => $actor->id,
            'waktu' => Carbon::now(),
            'tindakan' => $tindakan,
            'objek_tipe' => $objekTipe,
            'objek_id' => $objekId,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'alasan' => $alasan,
            'dasar_izin' => $dasarIzin,
        ]);
    }
}
