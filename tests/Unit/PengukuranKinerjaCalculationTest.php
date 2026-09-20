<?php

namespace Tests\Unit;

use App\Actions\Pengukuran\CalculatePengukuran;
use PHPUnit\Framework\TestCase;

class PengukuranKinerjaCalculationTest extends TestCase
{
    /** Nilai manual kanonis tetap nilai indikator; persentase target lama bukan mesin PRD. */
    public function test_manual_value_is_not_transformed_into_a_target_percentage(): void
    {
        $this->assertSame('76.25', (new CalculatePengukuran)->handle('manual', 2, [], [], 76.25)['nilai']);
    }

    public function test_snapshot_precision_controls_stored_value(): void
    {
        $engine = new CalculatePengukuran;
        $this->assertSame('76.3', $engine->handle('manual', 1, [], [], 76.25)['nilai']);
        $this->assertSame('76.25', $engine->handle('manual', 2, [], [], 76.25)['nilai']);
    }
}
