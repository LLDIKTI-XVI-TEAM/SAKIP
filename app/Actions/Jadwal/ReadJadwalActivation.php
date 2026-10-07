<?php

namespace App\Actions\Jadwal;

use App\Models\JadwalTahunan;
use App\Models\User;
use App\Policies\JadwalTahunanPolicy;
use App\Services\Jadwal\JadwalActivationReadiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReadJadwalActivation
{
    public function __construct(private readonly JadwalTahunanPolicy $policy, private readonly JadwalActivationReadiness $readiness) {}

    /** Pratinjau advisory tanpa lock aktivasi dan tanpa tulis; POST tetap menilai ulang. @return array<string, mixed> */
    public function handle(User $actor, string $jadwalId): array
    {
        // Izin diputuskan sebelum lookup agar UUID existing dan missing tidak dapat dibedakan.
        $this->policy->activate($actor)->authorize();

        return DB::transaction(function () use ($jadwalId): array {
            // Satu snapshot konsisten untuk seluruh sumber; bila dipanggil di dalam transaksi lain, isolasi milik caller.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            return $this->readiness->evaluate($this->readiness->context(JadwalTahunan::findOrFail($jadwalId)), CarbonImmutable::now());
        });
    }
}
