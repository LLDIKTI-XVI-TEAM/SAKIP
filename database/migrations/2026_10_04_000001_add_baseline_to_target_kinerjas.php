<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cutover mensyaratkan writer aplikasi/CLI berhenti sampai kode baru siap. Locks menutup scan–DDL. */
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->lockTables();
            $this->assertClean([
                'annual_negatif' => 't.target_tahunan < 0',
                'annual_NaN' => "t.target_tahunan = 'NaN'::numeric",
                'rentang_renstra' => 't.tahun < r.tahun_mulai OR t.tahun > r.tahun_selesai',
                'tahun_mulai_berlaku' => 't.tahun < i.tahun_mulai_berlaku',
            ]);
            DB::statement('ALTER TABLE target_kinerjas ALTER COLUMN target_tahunan TYPE numeric(30,12), ALTER COLUMN target_tahunan DROP NOT NULL, ALTER COLUMN target_tahunan DROP DEFAULT');
            Schema::table('target_kinerjas', function (Blueprint $table): void {
                $table->decimal('baseline', 30, 12)->nullable();
                $table->foreignUuid('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        });
    }

    /** Writer baru tetap berhenti sampai schema dan kode lama kompatibel; tidak membuang provenance/nilai. */
    public function down(): void
    {
        DB::transaction(function (): void {
            $this->lockTables();
            $this->assertClean([
                'baseline_terisi' => 't.baseline IS NOT NULL',
                'updated_by_terisi' => 't.updated_by IS NOT NULL',
                'annual_null' => 't.target_tahunan IS NULL',
                'annual_NaN' => "t.target_tahunan = 'NaN'::numeric",
                'annual_tidak_exact_14_2' => 'abs(t.target_tahunan) > 999999999999.99 OR t.target_tahunan <> trunc(t.target_tahunan, 2)',
            ]);
            DB::statement('ALTER TABLE target_kinerjas ALTER COLUMN target_tahunan TYPE numeric(14,2), ALTER COLUMN target_tahunan SET NOT NULL, ALTER COLUMN target_tahunan SET DEFAULT 0');
            Schema::table('target_kinerjas', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('updated_by');
                $table->dropColumn('baseline');
            });
        });
    }

    private function lockTables(): void
    {
        // pg_settings menormalisasi satuan menjadi ms; nol berarti tidak terbatas.
        $timeout = DB::selectOne("SELECT setting::bigint AS ms FROM pg_settings WHERE name = 'lock_timeout' AND unit = 'ms'");
        if ($timeout === null || $timeout->ms <= 0 || $timeout->ms > 5000) {
            DB::statement("SET LOCAL lock_timeout = '5s'");
        }
        $effective = DB::selectOne("SELECT setting::bigint AS ms FROM pg_settings WHERE name = 'lock_timeout' AND unit = 'ms'");
        if ($effective === null || $effective->ms <= 0 || $effective->ms > 5000) {
            throw new RuntimeException('Batas tunggu lock migrasi tidak dapat diverifikasi.');
        }
        // Batas berlaku per lock attempt, bukan durasi seluruh migrasi.
        DB::statement('LOCK TABLE renstras, sasaran_strategis, indikator_kinerjas, target_kinerjas IN ACCESS EXCLUSIVE MODE');
    }

    /** Diagnosis dibatasi per aturan; tidak mengubah data agar migrasi tampak berhasil. */
    private function assertClean(array $rules): void
    {
        $diagnostics = [];
        foreach ($rules as $rule => $predicate) {
            $query = DB::table('target_kinerjas as t')
                ->join('indikator_kinerjas as i', 'i.id', '=', 't.indikator_kinerja_id')
                ->join('sasaran_strategis as s', 's.id', '=', 'i.sasaran_strategis_id')
                ->join('renstras as r', 'r.id', '=', 's.renstra_id')
                ->whereRaw('('.$predicate.')');
            $count = $query->count();
            if ($count > 0) {
                $diagnostics[$rule] = ['count' => $count, 'examples' => $query->orderBy('t.id')->limit(20)->get(['t.id', 'i.id as indikator_id', 'r.id as renstra_id', 't.tahun'])->all()];
            }
        }
        if ($diagnostics !== []) {
            throw new RuntimeException('Migrasi target tahunan ditolak: '.json_encode($diagnostics, JSON_THROW_ON_ERROR));
        }
    }
};
