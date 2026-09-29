<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class StoreSasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PermissionResolver $resolver) {}

    /**
     * Membuat satu Sasaran di bawah Renstra terpilih beserta audit pembuatannya.
     * Otorisasi ditangani FormRequest/Policy di batas request; di sini hanya
     * mencatat dasar izin efektif untuk jejak audit.
     */
    public function handle(User $actor, array $validated): SasaranStrategis
    {
        $dasarIzin = $this->resolver->resolve($actor, PermissionCodes::SASARAN_CREATE)->toAuditBasis();

        return DB::transaction(function () use ($validated, $actor, $dasarIzin) {
            $created = SasaranStrategis::create([
                'renstra_id' => $validated['renstra_id'],
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? 0,
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat',
                objekTipe: 'sasaran',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah sasaran strategis '{$created->kode}'.",
                dasarIzin: $dasarIzin,
            );

            return $created;
        });
    }
}
