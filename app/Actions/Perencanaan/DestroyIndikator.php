<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class DestroyIndikator
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PermissionResolver $resolver) {}

    /**
     * Mengarsipkan Indikator (never-delete mutlak, ADR 0003).
     *
     * Tidak ada jalur hapus fisik: baris selalu dipertahankan dengan
     * `status = arsip` beserta audit `indikator.arsipkan`, baik memiliki
     * dependensi maupun tidak. Idempoten — pemanggilan ulang pada baris
     * yang sudah diarsipkan tetap tercatat sebagai audit baru agar jejak
     * percobaan pengarsipan utuh. Reaktivasi (arsip → aktif) tidak
     * disediakan di PR ini.
     *
     * Otorisasi ditangani FormRequest/Policy di batas request; di sini
     * hanya mencatat dasar izin efektif.
     *
     * @return array{diarsipkan: bool, kode: string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, string $alasan): array
    {
        $alasan = trim($alasan);
        $dasarIzin = $this->resolver->resolve($actor, PermissionCodes::INDIKATOR_DELETE)->toAuditBasis();

        return DB::transaction(function () use ($indikator, $actor, $alasan, $dasarIzin) {
            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::where('id', $indikator->id)->lockForUpdate()->firstOrFail();
            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // Never-delete: arsipkan baris apa pun kondisinya, jangan hapus fisik.
            $lockedIndikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.arsipkan',
                objekTipe: 'indikator',
                objekId: (string) $lockedIndikator->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $lockedIndikator->withoutRelations()->toArray(),
                alasan: $alasan,
                dasarIzin: $dasarIzin,
            );

            return ['diarsipkan' => true, 'kode' => $nilaiLama['kode']];
        });
    }
}
