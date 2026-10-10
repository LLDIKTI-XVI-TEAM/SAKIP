<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApprovePengukuran
{
    public function __construct(private PengukuranMutationSteps $steps) {}

    /** Pengesahan menulis metadata approval pada versi yang sama sekali, bersama header dan audit; payload beku tidak berubah. */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$decision) {
                [$actor, $p, , $period, $before] = $this->steps->lock($actor, $id, 'pengukuran:sahkan', 'sahkan', (int) $data['versi'], $decision);
                // F2: pengaju jalur Perencanaan boleh mengesahkan pengajuannya sendiri, tetapi jejaknya wajib tercatat.
                $version = $p->latestVersion;
                $selfApproval = $version->diajukan_by === $actor->id && $version->jalur_pengajuan === 'perencanaan';
                $version->update(['disahkan_by' => $actor->id, 'disahkan_at' => now()]);
                $p->status_alur = 'disahkan';

                return $this->steps->finish($actor, $p, 'pengukuran.sahkan', 'Mengesahkan pengukuran.', $decision, $before,
                    ['self_approval' => $selfApproval, ...$this->steps->reviewTrail($period)]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'sahkan', null, $decision, $exception);
            throw $exception;
        }
    }
}
