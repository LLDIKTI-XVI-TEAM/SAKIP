<?php

use App\Support\DeviasiTargetPk;

test('half_up dua sisi pada presisi snapshot', function (?string $skor, ?string $pk, string $status, ?string $skorBulat, ?string $pkBulat): void {
    expect(DeviasiTargetPk::bandingkan($skor, $pk, 2))->toBe(['status' => $status, 'skor' => $skorBulat, 'target_pk' => $pkBulat, 'presisi' => 2]);
})->with([
    'setara setelah bulat' => ['76.254', '76.25', 'setara', '76.25', '76.25'],
    'pk 3 desimal bulat ke atas' => ['76.26', '76.255', 'setara', '76.26', '76.26'],
    'skor bulat ke atas deviasi' => ['76.255', '76.25', 'deviasi', '76.26', '76.25'],
    'nol sah' => ['0', '0.000000000000', 'setara', '0.00', '0.00'],
    'target null' => ['76.25', null, 'tidak_dapat_dinilai', '76.25', null],
    'skor null' => [null, '76.25', 'tidak_dapat_dinilai', null, '76.25'],
]);
