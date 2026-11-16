<?php

namespace App\Actions\Jadwal;

use App\Models\JadwalTahunan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TutupJadwal
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit) {}

    /**
     * Transisi aktif → ditutup; hanya status, closed_at, dan revisi yang berubah.
     * Tanggal `penutupan` rencana, snapshot, dan sesi koreksi tidak disentuh.
     *
     * @param  array{expected_revisi: int|string, alasan: string}  $data
     */
    public function handle(User $actor, string $jadwalId, array $data): JadwalTahunan
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        $jadwalId = strtolower($jadwalId);
        try {
            return DB::transaction(function () use ($actor, $jadwalId, $data, &$decision): JadwalTahunan {
                $decision = $this->lockedActor->handle($actor, PermissionCodes::JADWAL_TUTUP)['keputusan'];
                if (! $decision->allowed) {
                    throw new AuthorizationException('Anda tidak memiliki izin menutup jadwal.');
                }
                $jadwal = JadwalTahunan::whereKey($jadwalId)->lockForUpdate()->firstOrFail();
                if ($jadwal->status !== 'aktif') {
                    throw ValidationException::withMessages(['jadwal' => 'Hanya jadwal berstatus aktif yang dapat ditutup.']);
                }
                if ($jadwal->revisi !== (int) $data['expected_revisi']) {
                    throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum menutup.']);
                }
                $before = $jadwal->only(['status', 'revisi', 'closed_at']);
                // Dipotong ke detik karena closed_at bertipe timestamp(0).
                $at = CarbonImmutable::now()->startOfSecond();
                $jadwal->forceFill(['status' => 'ditutup', 'closed_at' => $at->format('Y-m-d H:i:s'), 'revisi' => $jadwal->revisi + 1])->save();
                $this->audit->catat(actor: $actor, tindakan: 'jadwal.tutup', objekTipe: 'jadwal', objekId: $jadwal->id, nilaiLama: $before,
                    nilaiBaru: [...$jadwal->only(['status', 'revisi']), 'closed_at' => $at->toIso8601ZuluString(), 'penutupan' => $jadwal->penutupan->format('Y-m-d')],
                    alasan: AuditReason::sanitize($data['alasan']), dasarIzin: $decision->toAuditBasis());

                return $jadwal;
            });
        } catch (AuthorizationException|ValidationException $exception) {
            // Di luar rollback agar penolakan tetap tercatat dengan keputusan izin yang benar-benar dipakai.
            $this->audit->catat(actor: $actor, tindakan: 'jadwal.tutup_ditolak', objekTipe: 'jadwal', objekId: $jadwalId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_penutupan'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }
}
