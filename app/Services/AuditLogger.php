<?php

namespace App\Services;

use App\Actions\Audit\WriteAuditLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AlasanAudit;
use App\Support\AuditReason;
use InvalidArgumentException;

/**
 * Mencatat audit manual yang dipakai ulang oleh Action lintas domain, termasuk Renstra dan target tahunan.
 * Service memeriksa alasan sensitif, menyediakan fallback dan sanitasi khusus komponen, lalu
 * meneruskan penyimpanan serta sanitasi teks PostgreSQL kepada WriteAuditLog.
 * Action pemanggil tetap memiliki workflow, otorisasi, locking dan transaksi domain, serta
 * memilih snapshot perubahan dan dasar izin; Service ini tidak mengambil alih tanggung jawab tersebut.
 */
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
        $reasonMissing = $alasan === null || trim($alasan) === '';
        if (in_array($tindakan, self::SENSITIVE_ACTIONS, true) && $reasonMissing) {
            throw new InvalidArgumentException("Tindakan sensitif {$tindakan} wajib menyertakan alasan.");
        }

        $fallback = "Pencatatan audit untuk tindakan {$tindakan}.";
        // Kontrak alasan komponen berlaku untuk success maupun denial, tanpa
        // memangkas rujukan resmi pada audit domain lain. Semua jalur tetap
        // melalui sanitasi text PostgreSQL pada WriteAuditLog.
        $effectiveAlasan = $reasonMissing ? $fallback : $alasan;
        if (str_starts_with($tindakan, 'komponen.')) {
            $effectiveAlasan = AuditReason::sanitize(AlasanAudit::sanitasi($alasan, $fallback));
            if (trim($effectiveAlasan) === '') {
                $effectiveAlasan = $fallback;
            }
        }

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
