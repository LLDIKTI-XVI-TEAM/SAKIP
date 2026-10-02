<?php

use App\Services\Jadwal\JadwalDraftRules;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class);

/** @return array<string, mixed> */
function calendarDates(): array
{
    return [
        'tahun' => 2026,
        'rencana_aksi_mulai' => '2026-01-05',
        'rencana_aksi_selesai' => '2026-01-30',
        'penutupan' => '2027-01-19',
        'periode' => [
            ['periode_id' => 'a', 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07', 'reviu_mulai' => '2026-04-08', 'reviu_selesai' => '2026-04-15'],
            ['periode_id' => 'b', 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11', 'reviu_mulai' => '2027-01-12', 'reviu_selesai' => '2027-01-18'],
        ],
    ];
}

test('normal dates and cross year last period are valid', function (): void {
    expect(fn () => (new JadwalDraftRules)->validate(calendarDates(), ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]))->not->toThrow(ValidationException::class);
});

test('invalid calendar boundaries have field errors', function (string $field, string $value, string $error): void {
    $data = calendarDates();
    data_set($data, $field, $value);
    try {
        (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]);
        $this->fail('Kalender invalid diterima.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($error);
    }
})->with([
    ['periode.0.pengisian_selesai', '2026-03-31', 'periode.0.pengisian_selesai'],
    ['periode.0.reviu_mulai', '2026-04-06', 'periode.0.reviu_mulai'],
    ['periode.0.reviu_selesai', '2026-04-07', 'periode.0.reviu_selesai'],
    ['periode.1.pengisian_mulai', '2026-04-07', 'periode.1.pengisian_mulai'],
    ['rencana_aksi_selesai', '2026-04-01', 'rencana_aksi_selesai'],
    ['rencana_aksi_mulai', '2025-12-01', 'rencana_aksi_mulai'],
    ['periode.0.reviu_selesai', '2027-01-01', 'periode.0.reviu_selesai'],
    ['periode.1.reviu_selesai', '2028-01-01', 'periode.1.reviu_selesai'],
    ['penutupan', '2027-01-18', 'penutupan'],
]);

test('selected order must be unique without imposing chronological order', function (): void {
    $data = calendarDates();
    expect(fn () => (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 4], 'b' => ['urutan' => 4]]))
        ->toThrow(ValidationException::class);

    $data['periode'][1] = ['periode_id' => 'b', 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-03-07', 'reviu_mulai' => '2026-03-07', 'reviu_selesai' => '2026-04-05'];
    (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]);
    $data['rencana_aksi_selesai'] = '2026-03-01';
    expect(fn () => (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]))
        ->toThrow(ValidationException::class);
});
