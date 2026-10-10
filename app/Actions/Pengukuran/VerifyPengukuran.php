<?php

namespace App\Actions\Pengukuran;

use App\Models\PengukuranKinerja;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class VerifyPengukuran
{
    public function __construct(private PengukuranMutationSteps $steps) {}

    /** Verifikasi mengubah status di atas versi pengajuan yang sama; payload beku tidak ditulis ulang, jejak reviu dibekukan di audit. */
    public function handle(User $actor, string $id, array $data): PengukuranKinerja
    {
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$decision) {
                [$actor, $p, , $period, $before] = $this->steps->lock($actor, $id, 'pengukuran:verifikasi', 'verifikasi', (int) $data['versi'], $decision);
                // F2: pengaju jalur Perencanaan boleh mereviu pengajuannya sendiri, tetapi jejaknya wajib tercatat.
                $version = $p->latestVersion;
                $selfApproval = $version->diajukan_by === $actor->id && $version->jalur_pengajuan === 'perencanaan';
                $p->status_alur = 'diverifikasi';

                return $this->steps->finish($actor, $p, 'pengukuran.verifikasi', 'Memverifikasi pengukuran.', $decision, $before,
                    ['self_approval' => $selfApproval, ...$this->steps->reviewTrail($period)]);
            });
        } catch (Throwable $exception) {
            $this->steps->compensate($actor, $id, 'verifikasi', null, $decision, $exception);
            throw $exception;
        }
    }
}
