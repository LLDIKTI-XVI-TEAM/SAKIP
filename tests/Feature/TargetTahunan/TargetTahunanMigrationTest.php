<?php

namespace Tests\Feature\TargetTahunan;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\TargetTahunanFixtures;
use Tests\TestCase;

class TargetTahunanMigrationTest extends TestCase
{
    use RefreshDatabase, TargetTahunanFixtures;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_04_000001_add_baseline_to_target_kinerjas.php');
    }

    private function legacyRow(string $annual = '85.25', int $year = 2026): string
    {
        $actor = $this->calendarActor();
        $indicator = $this->targetIndicator($actor);
        $id = (string) Str::uuid();
        DB::table('target_kinerjas')->insert(['id' => $id, 'indikator_kinerja_id' => $indicator->id, 'tahun' => $year, 'target_tahunan' => $annual, 'target_tw1' => '12.34', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-02']);

        return $id;
    }

    #[DataProvider('invalidLegacy')]
    public function test_legacy_preflight_rejects_invalid_rows_atomically(string $annual, int $year, string $rule): void
    {
        $this->migration()->down();
        $id = $this->legacyRow($annual, $year);
        $before = DB::table('target_kinerjas')->where('id', $id)->first();
        try {
            $this->migration()->up();
            $this->fail('Preflight harus menolak legacy invalid.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($rule, $exception->getMessage());
            $this->assertStringContainsString($id, $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('target_kinerjas', 'baseline'));
        $this->assertEquals($before, DB::table('target_kinerjas')->where('id', $id)->first());
    }

    public static function invalidLegacy(): array
    {
        return [['-1', 2026, 'negatif'], ['NaN', 2026, 'NaN'], ['85.25', 2030, 'rentang_renstra'], ['85.25', 2025, 'tahun_mulai_berlaku']];
    }

    #[DataProvider('invalidStoredValues')]
    public function test_database_rejects_negative_and_nan_values(string $field, string $value): void
    {
        $id = $this->legacyRow();
        $before = DB::table('target_kinerjas')->where('id', $id)->first();
        try {
            DB::transaction(fn () => DB::table('target_kinerjas')->where('id', $id)->update([$field => $value]));
            $this->fail('Constraint database harus menolak angka negatif dan NaN.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0]);
            $this->assertStringContainsString('target_kinerjas_'.$field.'_valid_check', $exception->getMessage());
        }
        $this->assertEquals($before, DB::table('target_kinerjas')->where('id', $id)->first());
    }

    public static function invalidStoredValues(): array
    {
        return [['baseline', '-1'], ['baseline', 'NaN'], ['target_tahunan', '-1'], ['target_tahunan', 'NaN']];
    }

    public function test_database_preserves_nullable_nonnegative_decimal_range(): void
    {
        $id = $this->legacyRow();
        foreach ([null, '0.000000000000', '101.234567890123', '999999999999999999.999999999999'] as $value) {
            DB::table('target_kinerjas')->where('id', $id)->update(['baseline' => $value, 'target_tahunan' => $value]);
            $row = DB::table('target_kinerjas')->where('id', $id)->first();
            $this->assertSame($value, $row->baseline);
            $this->assertSame($value, $row->target_tahunan);
        }
    }

    public function test_preserves_legacy_zero_and_quarter_targets_and_allows_null(): void
    {
        $this->migration()->down();
        $ids = [$this->legacyRow('0'), $this->legacyRow('85.25')];
        $this->migration()->up();
        foreach ($ids as $index => $id) {
            $row = DB::table('target_kinerjas')->where('id', $id)->first();
            $this->assertSame($index === 0 ? '0.000000000000' : '85.250000000000', $row->target_tahunan);
            $this->assertNull($row->baseline);
            $this->assertNull($row->updated_by);
            $this->assertSame('12.34', $row->target_tw1);
            $this->assertSame('2026-01-02 00:00:00', $row->updated_at);
        }
        $this->migration()->down();
        $this->assertSame('85.25', DB::table('target_kinerjas')->where('id', $ids[1])->value('target_tahunan'));
        $this->migration()->up();
        DB::table('target_kinerjas')->where('id', $ids[0])->update(['target_tahunan' => null]);
        $this->assertNull(DB::table('target_kinerjas')->where('id', $ids[0])->value('target_tahunan'));
    }

    #[DataProvider('lossyValues')]
    public function test_down_rejects_every_lossy_case(string $field, ?string $value): void
    {
        $id = $this->legacyRow();
        if ($field === 'updated_by') {
            $value = DB::table('users')->value('id');
        }
        if ($value === 'NaN') {
            // Simulasikan schema rusak agar preflight down tetap diuji meski writer normal dilindungi CHECK.
            DB::statement('ALTER TABLE target_kinerjas DROP CONSTRAINT IF EXISTS target_kinerjas_target_tahunan_valid_check');
        }
        DB::table('target_kinerjas')->where('id', $id)->update([$field => $value]);
        $before = DB::table('target_kinerjas')->where('id', $id)->first();
        try {
            $this->migration()->down();
            $this->fail('Down harus menolak kehilangan informasi.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($id, $exception->getMessage());
            if ($value === 'NaN') {
                $this->assertStringContainsString('NaN', $exception->getMessage());
            }
        }
        $this->assertTrue(Schema::hasColumn('target_kinerjas', 'baseline'));
        $this->assertEquals($before, DB::table('target_kinerjas')->where('id', $id)->first());
    }

    public static function lossyValues(): array
    {
        return [['baseline', '74.2345'], ['updated_by', null], ['target_tahunan', null], ['target_tahunan', '76.251'], ['target_tahunan', '1000000000000'], ['target_tahunan', 'NaN']];
    }
}
