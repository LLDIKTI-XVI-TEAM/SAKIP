<?php

namespace Tests\Support;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\User;
use App\Support\TargetRenstraState;
use Illuminate\Testing\TestResponse;

/** Tanpa trait lain agar dapat digabung dengan fixture target PK, aktivasi jadwal, maupun pengukuran. */
trait TargetRenstraFixtures
{
    private function targetRenstraState(IndikatorKinerja $indikator): string
    {
        $indikator = $indikator->fresh();

        return TargetRenstraState::token($indikator, $indikator->sasaranStrategis->renstra, TargetRenstraState::values($indikator->targetRenstra));
    }

    /** @param array<int, ?string> $nilai @param array<string, mixed> $extra */
    private function simpanTargetRenstra(User $aktor, IndikatorKinerja $indikator, array $nilai, ?string $state = null, array $extra = []): TestResponse
    {
        return $this->actingAs($aktor)->from('/perencanaan/sasaran-indikator')->put("/perencanaan/indikator/{$indikator->id}/target-renstra",
            ['nilai' => $nilai, 'expected_state' => $state ?? $this->targetRenstraState($indikator), ...$extra]);
    }

    private function mulaiRevisi(User $aktor, string $renstraId): TestResponse
    {
        return $this->actingAs($aktor)->from('/perencanaan/sasaran-indikator')->post("/perencanaan/renstra/{$renstraId}/revisi-target");
    }

    /** @param array<string, array<int, ?string>> $nilai @param array<string, mixed> $extra */
    private function simpanDrafRevisi(User $aktor, string $renstraId, ?int $versi, array $nilai, array $extra = []): TestResponse
    {
        return $this->actingAs($aktor)->from('/perencanaan/sasaran-indikator')->put("/perencanaan/renstra/{$renstraId}/revisi-target",
            ['versi' => $versi, 'nilai' => $nilai, ...$extra]);
    }

    /** @param array<string, mixed> $extra */
    private function simpanRevisi(User $aktor, string $renstraId, int $versi, ?string $justifikasi, array $extra = []): TestResponse
    {
        return $this->actingAs($aktor)->from('/perencanaan/sasaran-indikator')->post("/perencanaan/renstra/{$renstraId}/revisi-target/simpan",
            ['versi' => $versi, 'justifikasi' => $justifikasi, ...$extra]);
    }

    /** @param array<string, mixed> $extra */
    private function batalRevisi(User $aktor, string $renstraId, int $versi, array $extra = []): TestResponse
    {
        return $this->actingAs($aktor)->from('/perencanaan/sasaran-indikator')->delete("/perencanaan/renstra/{$renstraId}/revisi-target", ['versi' => $versi, ...$extra]);
    }

    /** Mensimulasikan transisi Renstra di luar Action agar test fokus pada perilaku target Renstra. */
    private function ubahStatusRenstra(IndikatorKinerja $indikator, string $status): void
    {
        Renstra::query()->whereKey($indikator->fresh()->sasaranStrategis->renstra_id)->update(['status' => $status, 'is_aktif' => $status === Renstra::STATUS_AKTIF]);
    }

    /** @return array<int, ?string> */
    private function nilaiTersimpan(IndikatorKinerja $indikator): array
    {
        return TargetRenstraState::values($indikator->fresh()->targetRenstra);
    }
}
