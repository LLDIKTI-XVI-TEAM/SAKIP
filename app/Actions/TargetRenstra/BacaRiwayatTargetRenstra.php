<?php

namespace App\Actions\TargetRenstra;

use App\Models\AuditLog;
use App\Models\IndikatorKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Policies\TargetKinerjaPolicy;

/**
 * Riwayat revisi target Renstra adalah bagian data target, sehingga cukup indikator:read dan tidak tunduk pada audit:read.
 * Hanya field allowlist yang dikirim: tanpa email, dasar_izin, alasan baku, maupun id audit.
 */
class BacaRiwayatTargetRenstra
{
    // Batas 100 entri terbaru; ganti ke pagination bila `terpotong` pernah terjadi.
    private const BATAS = 100;

    public function __construct(private readonly TargetKinerjaPolicy $policy) {}

    /** @return array{items: list<array{waktu: string, aktor: ?string, tahun: int, nilai_lama: ?string, nilai_baru: ?string}>, terpotong: bool} */
    public function handle(User $actor, string $indikatorId): array
    {
        // Izin diputuskan sebelum lookup agar keberadaan indikator tidak bocor ke pengguna tanpa izin.
        $this->policy->view($actor)->authorize();
        $indikator = IndikatorKinerja::whereKey($indikatorId)->firstOrFail();

        $audits = AuditLog::query()->where('tindakan', 'target_renstra.simpan')->where('objek_tipe', 'target_renstra')
            ->whereIn('objek_id', TargetRenstra::select('id')->where('indikator_id', $indikator->id))
            ->with('actor:id,nama')->orderByDesc('waktu')->orderByDesc('id')->limit(self::BATAS + 1)->get();

        return [
            'items' => $audits->take(self::BATAS)->map(fn (AuditLog $audit): array => [
                'waktu' => $audit->waktu->toIso8601String(),
                'aktor' => $audit->actor?->nama,
                'tahun' => (int) $audit->nilai_baru['tahun'],
                'nilai_lama' => $audit->nilai_lama['nilai'] ?? null,
                'nilai_baru' => $audit->nilai_baru['nilai'] ?? null,
            ])->values()->all(),
            'terpotong' => $audits->count() > self::BATAS,
        ];
    }
}
