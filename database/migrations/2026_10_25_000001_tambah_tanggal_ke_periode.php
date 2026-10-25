<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tanggal standar periode bawaan seed; periode dengan nama lain dilengkapi lewat Master Periode. */
    private const STANDAR = [
        'Triwulan I' => [1, 1, 3, 31],
        'Triwulan II' => [4, 1, 6, 30],
        'Triwulan III' => [7, 1, 9, 30],
        'Triwulan IV' => [10, 1, 12, 31],
    ];

    public function up(): void
    {
        // Q70 §70.1: hari dan bulan tanpa tahun berlaku setiap tahun; wajib-isi untuk periode aktif dan larangan tumpang tindih ditegakkan aplikasi.
        Schema::table('periode', function (Blueprint $table): void {
            $table->smallInteger('bulan_mulai')->nullable();
            $table->smallInteger('hari_mulai')->nullable();
            $table->smallInteger('bulan_selesai')->nullable();
            $table->smallInteger('hari_selesai')->nullable();
        });
        // Batas hari memakai tahun non-kabisat agar tanggal sah setiap tahun, sehingga 29 Februari ditolak.
        $hariMaks = fn (string $bulan): string => "CASE $bulan WHEN 2 THEN 28 WHEN 4 THEN 30 WHEN 6 THEN 30 WHEN 9 THEN 30 WHEN 11 THEN 30 ELSE 31 END";
        DB::statement('ALTER TABLE periode ADD CONSTRAINT periode_tanggal_valid CHECK ('
            .'(bulan_mulai IS NULL AND hari_mulai IS NULL AND bulan_selesai IS NULL AND hari_selesai IS NULL) OR ('
            .'bulan_mulai IS NOT NULL AND hari_mulai IS NOT NULL AND bulan_selesai IS NOT NULL AND hari_selesai IS NOT NULL'
            .' AND bulan_mulai BETWEEN 1 AND 12 AND bulan_selesai BETWEEN 1 AND 12'
            .' AND hari_mulai BETWEEN 1 AND '.$hariMaks('bulan_mulai')
            .' AND hari_selesai BETWEEN 1 AND '.$hariMaks('bulan_selesai')
            .' AND bulan_mulai * 100 + hari_mulai <= bulan_selesai * 100 + hari_selesai))');
        foreach (self::STANDAR as $nama => [$bulanMulai, $hariMulai, $bulanSelesai, $hariSelesai]) {
            DB::table('periode')->where('nama', $nama)->update([
                'bulan_mulai' => $bulanMulai, 'hari_mulai' => $hariMulai, 'bulan_selesai' => $bulanSelesai, 'hari_selesai' => $hariSelesai,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE periode DROP CONSTRAINT IF EXISTS periode_tanggal_valid');
        Schema::table('periode', function (Blueprint $table): void {
            $table->dropColumn(['bulan_mulai', 'hari_mulai', 'bulan_selesai', 'hari_selesai']);
        });
    }
};
