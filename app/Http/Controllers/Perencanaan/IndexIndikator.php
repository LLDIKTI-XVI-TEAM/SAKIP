<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexIndikator extends Controller
{
    public function __invoke(Request $request): Response
    {
        $indikatorList = [
            [
                'id' => 1,
                'kode' => 'IKU-01',
                'nama' => 'Persentase Perguruan Tinggi Swasta (PTS) yang Terakreditasi Minimal Baik Sekali',
                'definisi_operasional' => 'Jumlah PTS binaan dengan peringkat akreditasi institusi minimal Baik Sekali atau B dibagi total PTS aktif di wilayah kerja LLDIKTI XVI dikalikan 100%.',
                'satuan' => '%',
                'tipe_perhitungan' => 'naik_baik',
                'jenis_agregasi' => 'terakhir',
                'sasaran_kode' => 'SS-01',
                'sasaran_deskripsi' => 'Meningkatnya Kualitas Layanan Lembaga Layanan Pendidikan Tinggi (LLDIKTI)',
                'unit_id' => 3,
                'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                'unit_kode' => 'POKJA-KLSI',
                'pic_nama' => 'PIC Pokja Kelembagaan',
                'tahun' => 2026,
                'target_tahunan' => 85.0,
                'target_tw1' => 70.0,
                'target_tw2' => 75.0,
                'target_tw3' => 80.0,
                'target_tw4' => 85.0,
                'is_aktif' => true,
            ],
            [
                'id' => 2,
                'kode' => 'IKU-02',
                'nama' => 'Persentase PTS yang Memiliki Masalah Sengketa Kelembagaan / Legalitas',
                'definisi_operasional' => 'Jumlah PTS yang sedang mengalami permasalahan hukum/sengketa internal yayasan dibagi total PTS binaan dikalikan 100%. Semakin rendah angka realisasi, semakin baik kinerja lembaga.',
                'satuan' => '%',
                'tipe_perhitungan' => 'turun_baik',
                'jenis_agregasi' => 'terakhir',
                'sasaran_kode' => 'SS-01',
                'sasaran_deskripsi' => 'Meningkatnya Kualitas Layanan Lembaga Layanan Pendidikan Tinggi (LLDIKTI)',
                'unit_id' => 3,
                'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                'unit_kode' => 'POKJA-KLSI',
                'pic_nama' => 'PIC Pokja Kelembagaan',
                'tahun' => 2026,
                'target_tahunan' => 5.0,
                'target_tw1' => 8.0,
                'target_tw2' => 7.0,
                'target_tw3' => 6.0,
                'target_tw4' => 5.0,
                'is_aktif' => true,
            ],
            [
                'id' => 3,
                'kode' => 'IKU-03',
                'nama' => 'Persentase Dosen Tetap PTS yang Berkualifikasi S3 / Doktor',
                'definisi_operasional' => 'Jumlah dosen tetap ber-NIDN pada PTS binaan yang telah berpendidikan strata 3 (S3/Doktor/Spesialis II) dibagi total dosen tetap dikalikan 100%.',
                'satuan' => '%',
                'tipe_perhitungan' => 'naik_baik',
                'jenis_agregasi' => 'terakhir',
                'sasaran_kode' => 'SS-02',
                'sasaran_deskripsi' => 'Meningkatnya Mutu Tata Kelola, Kualifikasi Dosen, dan Prestasi Mahasiswa PTS',
                'unit_id' => 5,
                'unit_nama' => 'Pokja Sumber Daya Perguruan Tinggi',
                'unit_kode' => 'POKJA-SDPT',
                'pic_nama' => 'Staf Teknis SDPT',
                'tahun' => 2026,
                'target_tahunan' => 40.0,
                'target_tw1' => 30.0,
                'target_tw2' => 32.0,
                'target_tw3' => 35.0,
                'target_tw4' => 40.0,
                'is_aktif' => true,
            ],
            [
                'id' => 4,
                'kode' => 'IKU-04',
                'nama' => 'Persentase Mahasiswa PTS yang Berprestasi Tingkat Nasional atau Internasional',
                'definisi_operasional' => 'Jumlah mahasiswa PTS yang berhasil meraih juara/penghargaan dalam kompetisi resmi Puspresnas/Belmawa/Internasional dibagi populasi sampel dikalikan 100%.',
                'satuan' => '%',
                'tipe_perhitungan' => 'naik_baik',
                'jenis_agregasi' => 'terakhir',
                'sasaran_kode' => 'SS-02',
                'sasaran_deskripsi' => 'Meningkatnya Mutu Tata Kelola, Kualifikasi Dosen, dan Prestasi Mahasiswa PTS',
                'unit_id' => 4,
                'unit_nama' => 'Pokja Akademik dan Kemahasiswaan',
                'unit_kode' => 'POKJA-AK',
                'pic_nama' => 'PIC Pokja Akademik',
                'tahun' => 2026,
                'target_tahunan' => 15.0,
                'target_tw1' => 10.0,
                'target_tw2' => 12.0,
                'target_tw3' => 13.0,
                'target_tw4' => 15.0,
                'is_aktif' => true,
            ],
            [
                'id' => 5,
                'kode' => 'IKU-05',
                'nama' => 'Indeks Kepuasan Masyarakat (IKM) atas Pelayanan Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
                'definisi_operasional' => 'Nilai agregat survei kepuasan layanan publik dari PTS, dosen, mahasiswa, dan stakeholder sesuai standar PermenPANRB No. 14 Tahun 2017.',
                'satuan' => 'Poin',
                'tipe_perhitungan' => 'naik_baik',
                'jenis_agregasi' => 'terakhir',
                'sasaran_kode' => 'SS-03',
                'sasaran_deskripsi' => 'Terwujudnya Tata Kelola Lembaga yang Akuntabel dan Berorientasi Pelayanan Prima',
                'unit_id' => 2,
                'unit_nama' => 'Bagian Umum',
                'unit_kode' => 'BAG-UMUM',
                'pic_nama' => 'Pengelola TI & Pengolah Data',
                'tahun' => 2026,
                'target_tahunan' => 90.0,
                'target_tw1' => 85.0,
                'target_tw2' => 86.0,
                'target_tw3' => 88.0,
                'target_tw4' => 90.0,
                'is_aktif' => true,
            ],
        ];

        $units = [
            ['id' => 2, 'nama' => 'Bagian Umum', 'kode' => 'BAG-UMUM'],
            ['id' => 3, 'nama' => 'Pokja Kelembagaan dan Sistem Informasi', 'kode' => 'POKJA-KLSI'],
            ['id' => 4, 'nama' => 'Pokja Akademik dan Kemahasiswaan', 'kode' => 'POKJA-AK'],
            ['id' => 5, 'nama' => 'Pokja Sumber Daya Perguruan Tinggi', 'kode' => 'POKJA-SDPT'],
        ];

        return Inertia::render('Indikator/Index', [
            'indikatorList' => $indikatorList,
            'units' => $units,
            'tahunAktif' => 2026,
        ]);
    }
}
