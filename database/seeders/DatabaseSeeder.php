<?php

namespace Database\Seeders;

use App\Models\IndikatorKinerja;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\UnitKerja;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles & Permissions
        $roles = [
            'superadmin' => 'Superadmin',
            'admin' => 'Admin Pengelola Sistem',
            'perencanaan' => 'Tim Perencanaan Kinerja',
            'pimpinan' => 'Pimpinan LLDIKTI',
            'pegawai' => 'Pegawai / PIC Indikator',
        ];

        foreach ($roles as $name => $label) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // 2. Seed Unit Kerja LLDIKTI XVI
        $lldikti = UnitKerja::create([
            'kode' => 'LLDIKTI16',
            'nama' => 'Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
            'singkatan' => 'LLDIKTI XVI',
            'parent_id' => null,
            'urutan' => 1,
            'is_active' => true,
        ]);

        $bagUmum = UnitKerja::create([
            'kode' => 'BAG-UMUM',
            'nama' => 'Bagian Umum',
            'singkatan' => 'Bag. Umum',
            'parent_id' => $lldikti->id,
            'urutan' => 2,
            'is_active' => true,
        ]);

        $pokjaKelembagaan = UnitKerja::create([
            'kode' => 'POKJA-KLSI',
            'nama' => 'Kelompok Kerja Kelembagaan dan Sistem Informasi',
            'singkatan' => 'Pokja Kelembagaan',
            'parent_id' => $lldikti->id,
            'urutan' => 3,
            'is_active' => true,
        ]);

        $pokjaAkademik = UnitKerja::create([
            'kode' => 'POKJA-AK',
            'nama' => 'Kelompok Kerja Akademik dan Kemahasiswaan',
            'singkatan' => 'Pokja Akademik',
            'parent_id' => $lldikti->id,
            'urutan' => 4,
            'is_active' => true,
        ]);

        $pokjaSdpt = UnitKerja::create([
            'kode' => 'POKJA-SDPT',
            'nama' => 'Kelompok Kerja Sumber Daya Perguruan Tinggi',
            'singkatan' => 'Pokja SDPT',
            'parent_id' => $lldikti->id,
            'urutan' => 5,
            'is_active' => true,
        ]);

        // 3. Seed Demo Users per Role
        $superadmin = User::create([
            'name' => 'Superadmin SAKIP',
            'email' => 'superadmin@lldikti16.kemdikbud.go.id',
            'nip' => '198001012005011001',
            'jabatan' => 'Pranata Komputer Ahli Madya',
            'unit_kerja_id' => $bagUmum->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $superadmin->assignRole('superadmin');

        $admin = User::create([
            'name' => 'Admin Operator',
            'email' => 'admin@lldikti16.kemdikbud.go.id',
            'nip' => '198502022008011002',
            'jabatan' => 'Pengelola TI & Pengolah Data',
            'unit_kerja_id' => $bagUmum->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $perencanaan = User::create([
            'name' => 'Koordinator Perencanaan',
            'email' => 'perencanaan@lldikti16.kemdikbud.go.id',
            'nip' => '198203032006022003',
            'jabatan' => 'Perencana Ahli Muda',
            'unit_kerja_id' => $bagUmum->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $perencanaan->assignRole('perencanaan');

        $pimpinan = User::create([
            'name' => 'Kepala LLDIKTI XVI',
            'email' => 'pimpinan@lldikti16.kemdikbud.go.id',
            'nip' => '197504041999031004',
            'jabatan' => 'Kepala LLDIKTI Wilayah XVI',
            'unit_kerja_id' => $lldikti->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $pimpinan->assignRole('pimpinan');

        $picKelembagaan = User::create([
            'name' => 'PIC Pokja Kelembagaan',
            'email' => 'pic.kelembagaan@lldikti16.kemdikbud.go.id',
            'nip' => '199005052014021005',
            'jabatan' => 'Staf Teknis Kelembagaan',
            'unit_kerja_id' => $pokjaKelembagaan->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $picKelembagaan->assignRole('pegawai');

        $picAkademik = User::create([
            'name' => 'PIC Pokja Akademik',
            'email' => 'pic.akademik@lldikti16.kemdikbud.go.id',
            'nip' => '199206062015032006',
            'jabatan' => 'Staf Teknis Akademik',
            'unit_kerja_id' => $pokjaAkademik->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $picAkademik->assignRole('pegawai');

        // 4. Seed Renstra 2025-2029 (Aktif)
        $renstra = Renstra::create([
            'kode' => 'RENSTRA-2025-2029',
            'nama' => 'Rencana Strategis LLDIKTI Wilayah XVI Tahun 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'deskripsi' => 'Rencana strategis pembangunan dan peningkatan mutu pendidikan tinggi di LLDIKTI XVI.',
            'is_aktif' => true,
        ]);

        // 5. Seed Sasaran Strategis Riil
        $ss1 = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-01',
            'deskripsi' => 'Meningkatnya Kualitas Layanan Lembaga Layanan Pendidikan Tinggi (LLDIKTI)',
            'urutan' => 1,
        ]);

        $ss2 = SasaranStrategis::create([
            'renstra_id' => $renstra->id,
            'kode' => 'SS-02',
            'deskripsi' => 'Meningkatnya Mutu Tata Kelola Perguruan Tinggi Swasta di Wilayah XVI',
            'urutan' => 2,
        ]);

        // 6. Seed Indikator Kinerja Utama (IKU Riil dari Excel 2026)
        // IKU 1: naik_baik (Persentase PTS Terakreditasi Minimal Baik Sekali)
        $iku1 = IndikatorKinerja::create([
            'sasaran_strategis_id' => $ss1->id,
            'kode' => 'IKU-01',
            'nama' => 'Persentase Perguruan Tinggi Swasta (PTS) yang Terakreditasi Minimal Baik Sekali',
            'definisi_operasional' => 'Jumlah PTS binaan dengan akreditasi minimal Baik Sekali atau B dibagi total PTS aktif dikali 100%.',
            'satuan' => '%',
            'tipe_perhitungan' => 'naik_baik',
            'jenis_agregasi' => 'terakhir',
            'is_aktif' => true,
        ]);

        TargetKinerja::create([
            'indikator_kinerja_id' => $iku1->id,
            'tahun' => 2026,
            'target_tahunan' => 85.00,
            'target_tw1' => 70.00,
            'target_tw2' => 75.00,
            'target_tw3' => 80.00,
            'target_tw4' => 85.00,
        ]);

        // IKU 2: turun_baik (Persentase PTS Bermasalah Sengketa Kelembagaan)
        $iku2 = IndikatorKinerja::create([
            'sasaran_strategis_id' => $ss2->id,
            'kode' => 'IKU-02',
            'nama' => 'Persentase PTS yang Memiliki Masalah Sengketa Kelembagaan / Legalitas',
            'definisi_operasional' => 'Jumlah PTS yang sedang mengalami sengketa internal yayasan atau izin operasional bermasalah dibagi total PTS binaan dikali 100%. Semakin kecil angka realisasi semakin baik kinerja instansi.',
            'satuan' => '%',
            'tipe_perhitungan' => 'turun_baik',
            'jenis_agregasi' => 'terakhir',
            'is_aktif' => true,
        ]);

        TargetKinerja::create([
            'indikator_kinerja_id' => $iku2->id,
            'tahun' => 2026,
            'target_tahunan' => 5.00,
            'target_tw1' => 8.00,
            'target_tw2' => 7.00,
            'target_tw3' => 6.00,
            'target_tw4' => 5.00,
        ]);

        // 7. Seed Periode Jadwal Aktif Triwulan I 2026
        $periodeTw1 = PeriodeJadwal::create([
            'tahun' => 2026,
            'triwulan' => 1,
            'nama_periode' => 'Pelaporan Kinerja Triwulan I 2026',
            'tanggal_mulai' => Carbon::now()->subDays(15),
            'tanggal_selesai' => Carbon::now()->addDays(30), // Aktif buka
            'status' => 'buka',
            'is_tahun_ditutup' => false,
        ]);

        // 8. Seed Penugasan Indikator ke Pokja Kelembagaan (PIC picKelembagaan)
        $penugasan1 = PenugasanIndikator::create([
            'indikator_kinerja_id' => $iku1->id,
            'unit_kerja_id' => $pokjaKelembagaan->id,
            'user_id' => $picKelembagaan->id,
            'tahun' => 2026,
            'is_active' => true,
        ]);

        $penugasan2 = PenugasanIndikator::create([
            'indikator_kinerja_id' => $iku2->id,
            'unit_kerja_id' => $pokjaKelembagaan->id,
            'user_id' => $picKelembagaan->id,
            'tahun' => 2026,
            'is_active' => true,
        ]);

        // 9. Seed Baris Pengukuran Kinerja Siap Input
        PengukuranKinerja::create([
            'penugasan_indikator_id' => $penugasan1->id,
            'periode_jadwal_id' => $periodeTw1->id,
            'target' => 70.00,
            'realisasi' => null,
            'capaian_persen' => null,
            'status' => 'draft',
        ]);

        PengukuranKinerja::create([
            'penugasan_indikator_id' => $penugasan2->id,
            'periode_jadwal_id' => $periodeTw1->id,
            'target' => 8.00,
            'realisasi' => null,
            'capaian_persen' => null,
            'status' => 'draft',
        ]);
    }
}
