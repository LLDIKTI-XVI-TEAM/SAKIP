<?php

namespace App\Actions\TargetRenstra;

use App\Models\RevisiTargetRenstra;
use App\Models\User;
use App\Services\TargetRenstra\GerbangRevisiTargetRenstra;

/** Membuka draf revisi, atau melanjutkan draf yang sudah terbuka; tidak diaudit karena belum mengubah target. */
class MulaiRevisiTargetRenstra
{
    public function __construct(private readonly GerbangRevisiTargetRenstra $gerbang) {}

    public function handle(User $actor, string $renstraId): RevisiTargetRenstra
    {
        return $this->gerbang->jalankan($actor, $renstraId, 'mulai', function () use ($actor, $renstraId): RevisiTargetRenstra {
            // Lock eksklusif menserialkan pembukaan draf pertama per Renstra.
            $renstra = $this->gerbang->renstraAktif($renstraId, eksklusif: true);

            return RevisiTargetRenstra::firstOrCreate(['renstra_id' => $renstra->id, 'status' => RevisiTargetRenstra::STATUS_DRAF], ['versi' => 1, 'dibuat_oleh' => $actor->id]);
        });
    }
}
