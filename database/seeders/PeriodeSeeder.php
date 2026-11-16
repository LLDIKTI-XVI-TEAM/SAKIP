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

            $triwulan = [
                'Triwulan I' => [1, 1, 3, 31],
                'Triwulan II' => [4, 1, 6, 30],
                'Triwulan III' => [7, 1, 9, 30],
                'Triwulan IV' => [10, 1, 12, 31],
            ];
            foreach (array_keys($triwulan) as $index => $nama) {
                [$bulanMulai, $hariMulai, $bulanSelesai, $hariSelesai] = $triwulan[$nama];
                Periode::create([
                    'nama' => $nama,
                    'urutan' => $index + 1,
                    'aktif' => true,
                    'is_nilai_akhir' => $index === 3,
                    'bulan_mulai' => $bulanMulai,
                    'hari_mulai' => $hariMulai,
                    'bulan_selesai' => $bulanSelesai,
                    'hari_selesai' => $hariSelesai,
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
