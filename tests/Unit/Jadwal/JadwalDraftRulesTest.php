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
        'penutupan' => '2027-01-19',
        'periode' => [
            ['periode_id' => 'a', 'pengisian_mulai' => '2026-04-01', 'pengisian_selesai' => '2026-04-07'],
            ['periode_id' => 'b', 'pengisian_mulai' => '2027-01-04', 'pengisian_selesai' => '2027-01-11'],
        ],
    ];
}

test('normal dates and cross year last period are valid', function (): void {
    expect(fn () => (new JadwalDraftRules)->validate(calendarDates(), ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]))->not->toThrow(ValidationException::class);
});

test('jendela hanya memuat tanggal pengisian', function (): void {
    expect(JadwalDraftRules::WINDOW_FIELDS)->toBe(['pengisian_mulai', 'pengisian_selesai']);
});

test('penutupan sehari setelah pengisian selesai terakhir sudah sah', function (): void {
    $data = [...calendarDates(), 'penutupan' => '2027-01-12'];
    expect(fn () => (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]))->not->toThrow(ValidationException::class);
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
    ['periode.1.pengisian_mulai', '2026-04-07', 'periode.1.pengisian_mulai'],
    ['periode.0.pengisian_selesai', '2027-01-01', 'periode.0.pengisian_selesai'],
    ['periode.1.pengisian_selesai', '2028-01-01', 'periode.1.pengisian_selesai'],
    ['penutupan', '2027-01-11', 'penutupan'],
    ['periode.1.pengisian_selesai', '2027-01-19', 'penutupan'],
]);

test('selected order must be unique without imposing chronological order', function (): void {
    $data = calendarDates();
    expect(fn () => (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 4], 'b' => ['urutan' => 4]]))
        ->toThrow(ValidationException::class);

    $data['periode'][1] = ['periode_id' => 'b', 'pengisian_mulai' => '2026-03-01', 'pengisian_selesai' => '2026-03-07'];
    (new JadwalDraftRules)->validate($data, ['a' => ['urutan' => 1], 'b' => ['urutan' => 4]]);
});
