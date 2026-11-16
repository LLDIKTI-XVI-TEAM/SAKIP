<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaveDraftPengukuran
{
    public function __construct(private PengukuranMutationSteps $steps) {}

    /** Draf menyimpan input yang boleh belum lengkap tanpa versi beku; satu kenaikan versi header dan satu audit `pengukuran.draft`. */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $path = null;
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$path, &$decision) {
                [$actor, $p, $snapshot, , $before] = $this->steps->lock($actor, $id, 'pengukuran:update', 'draft', (int) $data['versi'], $decision);
                $this->steps->storeInput($actor, $p, $snapshot, $data, $path, $decision);
                $p->status_alur = 'draft';

                return $this->steps->finish($actor, $p, 'pengukuran.draft', 'Menyimpan draf pengukuran.', $decision, $before, ['self_approval' => false]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'draft', $path, $decision, $exception);
            throw $exception;
        }
    }
}
