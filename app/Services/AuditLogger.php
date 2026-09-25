<?php

namespace App\Services;

use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use InvalidArgumentException;

class AuditLogger
{
    protected const SENSITIVE_ACTIONS = [
        'jenis_berkas.ubah',
        'jenis_berkas.hapus',
        'komponen.ubah',
        'komponen.hapus',
    ];

    public function __construct(private readonly WriteAuditLog $writeAuditLog) {}

    /**
     * @param  array<string, mixed>|null  $nilaiLama
     * @param  array<string, mixed>|null  $nilaiBaru
     * @param  array<string, mixed>|null  $dasarIzin
     */
    public function catat(
        User $actor,
        string $tindakan,
        string $objekTipe,
        string $objekId,
        ?array $nilaiLama = null,
        ?array $nilaiBaru = null,
        ?string $alasan = null,
        ?array $dasarIzin = null,
    ): AuditLog {
        if (in_array($tindakan, self::SENSITIVE_ACTIONS, true)) {
            if (empty($alasan) || trim($alasan) === '') {
                throw new InvalidArgumentException("Tindakan sensitif {$tindakan} wajib menyertakan alasan.");
            }
        }

        $effectiveAlasan = (! empty($alasan) && trim($alasan) !== '')
            ? $alasan
            : "Pencatatan audit untuk tindakan {$tindakan}.";

        return $this->writeAuditLog->handle([
            'actor_id' => $actor->id,
            'actor_type' => 'user',
            'sumber' => 'manual',
            'tindakan' => $tindakan,
            'objek_tipe' => $objekTipe,
            'objek_id' => $objekId,
            'nilai_lama' => $nilaiLama,
            'nilai_baru' => $nilaiBaru,
            'alasan' => $effectiveAlasan,
            'dasar_izin' => $dasarIzin,
        ]);
    }
}
