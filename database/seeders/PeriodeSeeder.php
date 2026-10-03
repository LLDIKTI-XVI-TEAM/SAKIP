<?php

namespace Database\Seeders;

use App\Models\Periode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeriodeSeeder extends Seeder
{
    /** Bootstrap master kosong; konfigurasi pengelola tidak disinkronkan ulang ke default. */
    public function run(): void
    {
        $created = DB::transaction(function (): bool {
            // Lock yang sama dengan mutasi master menjaga cek kosong dan empat insert tetap utuh.
            Periode::lockConfiguration(exclusive: true);
            if (Periode::exists()) {
                return false;
            }

            foreach (['Triwulan I', 'Triwulan II', 'Triwulan III', 'Triwulan IV'] as $index => $nama) {
                Periode::create([
                    'nama' => $nama,
                    'urutan' => $index + 1,
                    'aktif' => true,
                    'is_nilai_akhir' => $index === 3,
                ]);
            }

            return true;
        });

        if ($created) {
            $this->command?->info('Master periode awal Triwulan I–IV berhasil dibuat; Triwulan IV menjadi nilai akhir.');
        } else {
            $this->command?->warn('Seed periode dilewati: konfigurasi yang sudah ada dipertahankan. Kelola perubahan melalui halaman Master Periode.');
        }
    }
}
