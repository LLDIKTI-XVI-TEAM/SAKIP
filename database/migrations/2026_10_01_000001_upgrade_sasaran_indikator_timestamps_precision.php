<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tabel yang kolom timestamp-nya dinaikkan ke presisi mikrodetik.
     * Dibekukan lokal agar fresh-migrate masa depan deterministik.
     *
     * @var list<string>
     */
    private const TABLES = ['sasaran_strategis', 'indikator_kinerjas'];

    /**
     * Kolom timestamp yang dinaikkan presisinya (satu paket per tabel).
     *
     * @var list<string>
     */
    private const COLUMNS = ['created_at', 'updated_at'];

    /**
     * Naikkan created_at/updated_at ke timestamp(6) agar token guard
     * usang (expected_updated_at) monotonik dalam satu detik yang sama.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamp(6) without time zone',
                    $table,
                    $column
                ));
            }
        }

        foreach (self::TABLES as $table) {
            DB::statement(sprintf(
                'UPDATE %s SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL',
                $table
            ));
            DB::statement(sprintf(
                'UPDATE %s SET updated_at = created_at WHERE updated_at IS NULL',
                $table
            ));
        }

        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s SET DEFAULT CURRENT_TIMESTAMP',
                    $table,
                    $column
                ));
            }
        }
    }

    /**
     * Kembalikan created_at/updated_at ke presisi detik.
     */
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s DROP DEFAULT',
                    $table,
                    $column
                ));
            }
        }

        foreach (self::TABLES as $table) {
            foreach (self::COLUMNS as $column) {
                DB::statement(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s TYPE timestamp(0) without time zone',
                    $table,
                    $column
                ));
            }
        }
    }
};
