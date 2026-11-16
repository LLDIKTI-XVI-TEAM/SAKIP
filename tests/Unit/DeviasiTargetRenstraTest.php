<?php

use App\Support\DeviasiTargetRenstra;

test('deviasi menurut arah dengan nilai tersimpan apa adanya', function (string $arah, ?string $pk, ?string $renstra, string $status): void {
    expect(DeviasiTargetRenstra::status($arah, $pk, $renstra))->toBe($status);
})->with([
    'naik_baik di bawah' => ['naik_baik', '79.99', '80', DeviasiTargetRenstra::MENYIMPANG],
    'naik_baik sama' => ['naik_baik', '80', '80', DeviasiTargetRenstra::SESUAI],
    'naik_baik di atas' => ['naik_baik', '80.01', '80', DeviasiTargetRenstra::SESUAI],
    'turun_baik di atas' => ['turun_baik', '80.01', '80', DeviasiTargetRenstra::MENYIMPANG],
    'turun_baik sama' => ['turun_baik', '80', '80', DeviasiTargetRenstra::SESUAI],
    'turun_baik di bawah' => ['turun_baik', '79.99', '80', DeviasiTargetRenstra::SESUAI],
    'target Renstra belum ada' => ['naik_baik', '10', null, DeviasiTargetRenstra::RENSTRA_BELUM_ADA],
    'keduanya kosong' => ['turun_baik', null, null, DeviasiTargetRenstra::RENSTRA_BELUM_ADA],
    'target PK belum diisi' => ['naik_baik', null, '80', DeviasiTargetRenstra::TIDAK_DINILAI],
    'target Renstra nol naik_baik' => ['naik_baik', '0', '0', DeviasiTargetRenstra::SESUAI],
    'target Renstra nol turun_baik' => ['turun_baik', '0.01', '0', DeviasiTargetRenstra::MENYIMPANG],
    'skala tersimpan berbeda' => ['naik_baik', '80', '80.000000000000', DeviasiTargetRenstra::SESUAI],
    'tanpa toleransi presisi' => ['naik_baik', '79.999999999999', '80', DeviasiTargetRenstra::MENYIMPANG],
]);

test('arah di luar kontrak gagal tertutup', function (): void {
    DeviasiTargetRenstra::status('stabil', '80', '80');
})->throws(UnhandledMatchError::class);
