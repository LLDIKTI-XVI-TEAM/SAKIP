<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class UpdateSasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PermissionResolver $resolver) {}

    /**
     * Memperbarui kode/deskripsi/urutan Sasaran. Baris dikunci dulu lalu
     * nilai lama diambil dari baris terkunci agar audit before/after sesuai
     * state database sesaat sebelum mutasi (bukan dari route binding).
     */
    public function handle(User $actor, SasaranStrategis $sasaran, array $validated): SasaranStrategis
    {
        $dasarIzin = $this->resolver->resolve($actor, PermissionCodes::SASARAN_UPDATE)->toAuditBasis();

        return DB::transaction(function () use ($sasaran, $validated, $actor, $dasarIzin) {
            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::query()
                ->whereKey($sasaran->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();

            $lockedSasaran->update([
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? $lockedSasaran->urutan,
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.ubah',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $lockedSasaran->withoutRelations()->toArray(),
                alasan: "Memperbarui sasaran strategis '{$lockedSasaran->kode}'.",
                dasarIzin: $dasarIzin,
            );

            return $lockedSasaran;
        });
    }
}
