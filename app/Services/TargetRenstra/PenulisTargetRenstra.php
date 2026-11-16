<?php

namespace App\Services\TargetRenstra;

use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Satu jalur tulis untuk simpan langsung (Renstra draft) dan simpan revisi (Renstra aktif), sehingga audit
 * `target_renstra.simpan` yang menjadi sumber riwayat per indikator tetap lengkap untuk keduanya.
 */
class PenulisTargetRenstra
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Pemanggil memegang lock baris target dan menormalkan nilai; `$current` null berarti pengisian pertama.
     *
     * @param  array<string, mixed>  $dasarIzin
     * @param  array<string, mixed>  $konteks
     */
    public function tulis(User $actor, string $indikatorId, int $tahun, ?TargetRenstra $current, ?string $lama, ?string $baru, array $dasarIzin, ?string $alasan = null, array $konteks = []): void
    {
        $row = $current ?? new TargetRenstra(['indikator_id' => $indikatorId, 'tahun' => $tahun]);
        $row->fill(['nilai' => $baru, 'updated_by' => $actor->id])->save();
        $this->audit->catat(actor: $actor, tindakan: 'target_renstra.simpan', objekTipe: 'target_renstra', objekId: $row->id,
            nilaiLama: $current ? ['indikator_id' => $indikatorId, 'tahun' => $tahun, 'nilai' => $lama] : null,
            nilaiBaru: ['indikator_id' => $indikatorId, 'tahun' => $tahun, 'nilai' => $baru, ...$konteks], alasan: $alasan, dasarIzin: $dasarIzin);
    }
}
