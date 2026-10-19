<?php

use App\Support\StatusTampilanPengukuran as S;

$disahkan = fn (string $perhitungan, bool $tanpaTarget = false): array => ['status_perhitungan' => $perhitungan, 'tanpa_target_periode' => $tanpaTarget];

test('status tampilan indikator × periode mengikuti urutan prioritas', function (bool $dibuka, bool $berlaku, ?string $alur, bool $lewat, bool $lampau, ?array $versi, string $harapan): void {
    expect(S::tentukan($dibuka, $berlaku, $alur, $lewat, $lampau, $versi))->toBe($harapan);
})->with([
    'belum dibuka mengalahkan semuanya' => [false, false, 'disahkan', true, false, $disahkan('terhitung'), S::BELUM_DIBUKA],
    'sebelum efektif walau ada header' => [true, false, 'draft', false, false, null, S::TIDAK_BERLAKU],
    'tanpa header' => [true, true, null, true, false, null, S::BELUM_MENGISI],
    'draft lewat jendela' => [true, true, 'draft', true, false, null, S::TIDAK_MENGISI],
    'draft lewat jendela periode lampau' => [true, true, 'draft', true, true, null, S::DALAM_PROSES],
    'draft dalam jendela' => [true, true, 'draft', false, false, null, S::DALAM_PROSES],
    'diajukan' => [true, true, 'diajukan', true, false, null, S::DALAM_PROSES],
    'diverifikasi' => [true, true, 'diverifikasi', false, false, null, S::DALAM_PROSES],
    'dikembalikan lewat jendela' => [true, true, 'dikembalikan', true, false, null, S::DALAM_PROSES],
    'belum dapat diukur menang atas tanpa target' => [true, true, 'disahkan', true, false, $disahkan('belum_dapat_diukur', true), S::BELUM_DAPAT_DIUKUR],
    'tanpa target terhitung' => [true, true, 'disahkan', true, false, $disahkan('terhitung', true), S::TANPA_TARGET_PERIODE],
    'tanpa target tidak dapat dihitung' => [true, true, 'disahkan', true, false, $disahkan('tidak_dapat_dihitung', true), S::TANPA_TARGET_PERIODE],
    'tidak dapat dihitung bertarget' => [true, true, 'disahkan', true, false, $disahkan('tidak_dapat_dihitung'), S::TIDAK_DAPAT_DIHITUNG],
    'disahkan terhitung bertarget' => [true, true, 'disahkan', true, false, $disahkan('terhitung'), S::BELUM_DITETAPKAN],
    'disahkan tanpa versi disahkan tidak tampil resmi' => [true, true, 'disahkan', true, false, null, S::DALAM_PROSES],
]);

test('hanya belum dibuka dan tidak berlaku yang dikeluarkan dari hitungan kewajiban', function (): void {
    expect(S::DIHITUNG)->not->toContain(S::BELUM_DIBUKA)->not->toContain(S::TIDAK_BERLAKU)
        ->and(S::DIHITUNG)->toHaveCount(7);
});
