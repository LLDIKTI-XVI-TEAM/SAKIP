<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Deteksi dan selesaikan duplikasi nama unit (case-insensitive) sebelum membuat indeks unik
        $duplicates = DB::table('unit')
            ->selectRaw('LOWER(nama) as lower_nama, COUNT(*) as count')
            ->groupByRaw('LOWER(nama)')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $units = DB::table('unit')
                ->whereRaw('LOWER(nama) = ?', [$duplicate->lower_nama])
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            // Baris pertama tetap memakai nama asli, baris berikutnya didisambiguasi
            $index = 1;
            foreach ($units->slice(1) as $dupUnit) {
                do {
                    $suffix = " (Duplikat {$index})";
                    $baseName = mb_substr($dupUnit->nama, 0, 255 - mb_strlen($suffix));
                    $candidateName = $baseName.$suffix;
                    $index++;
                } while (DB::table('unit')->whereRaw('LOWER(nama) = ?', [mb_strtolower($candidateName)])->exists());

                DB::table('unit')->where('id', $dupUnit->id)->update(['nama' => $candidateName]);
            }
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS unit_nama_lower_unique ON unit (LOWER(nama))');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unit_nama_lower_unique');
    }
};
