<?php

namespace Tests\Unit;

use App\Support\TargetTahunanDecimal;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TargetTahunanDecimalTest extends TestCase
{
    #[DataProvider('validValues')]
    public function test_normalizes_without_float_or_rounding(?string $raw, ?string $expected): void
    {
        $this->assertSame($expected, TargetTahunanDecimal::normalize($raw));
    }

    public static function validValues(): array
    {
        return [[null, null], ['', null], ['  ', null], ['0', '0'], ['000.000', '0'], [' 74,234500 ', '74.2345'], ['76.2500', '76.25'], ['124', '124'], ['999999999999999999.999999999999', '999999999999999999.999999999999']];
    }

    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_capacity_and_syntax(string $raw): void
    {
        $this->expectException(ValidationException::class);
        TargetTahunanDecimal::normalize($raw);
    }

    public static function invalidValues(): array
    {
        return array_map(fn ($value) => [$value], ['-1', '-0', '+1', '1e2', 'NaN', 'Infinity', '1,234.56', '1 234', '.2', '2.', '1000000000000000000', '0.0000000000001']);
    }

    public function test_precision_rejects_meaningful_digits_without_rounding(): void
    {
        TargetTahunanDecimal::assertPrecision(TargetTahunanDecimal::normalize('76.2500'), 2);
        TargetTahunanDecimal::assertPrecision(null, 0);
        $this->expectException(ValidationException::class);
        TargetTahunanDecimal::assertPrecision('76.251', 2);
    }
}
