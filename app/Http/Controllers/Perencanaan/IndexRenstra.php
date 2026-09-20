<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexRenstra extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Mockup Data Renstra & Cascading Kinerja LLDIKTI Wilayah XVI
        $renstraAktif = [
            'id' => 1,
            'kode' => 'RENSTRA-2025-2029',
            'nama' => 'Rencana Strategis LLDIKTI Wilayah XVI Tahun 2025-2029',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2029,
            'deskripsi' => 'Rencana strategis pembangunan dan peningkatan mutu pendidikan tinggi di LLDIKTI Wilayah XVI (Gorontalo, Sulawesi Utara, Sulawesi Tengah).',
            'dasar_hukum' => 'Permendikbudristek No. 35 Tahun 2021 tentang OTK LLDIKTI dan Rencana Pembangunan Jangka Menengah Nasional (RPJMN) 2025-2029.',
            'is_aktif' => true,
            'total_sasaran' => 3,
            'total_indikator' => 5,
        ];

        $sasaranStrategisList = [
            [
                'id' => 1,
                'kode' => 'SS-01',
                'deskripsi' => 'Meningkatnya Kualitas Layanan Lembaga Layanan Pendidikan Tinggi (LLDIKTI) kepada PTS dan Pemangku Kepentingan',
                'urutan' => 1,
                'indikator' => [
                    [
                        'id' => 1,
                        'kode' => 'IKU-01',
                        'nama' => 'Persentase Perguruan Tinggi Swasta (PTS) yang Terakreditasi Minimal Baik Sekali',
                        'satuan' => '%',
                        'tipe_perhitungan' => 'naik_baik',
                        'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                        'unit_kode' => 'POKJA-KLSI',
                        'target_tahunan' => 85.0,
                        'tw1' => 70.0,
                        'tw2' => 75.0,
                        'tw3' => 80.0,
                        'tw4' => 85.0,
                    ],
                    [
                        'id' => 2,
                        'kode' => 'IKU-02',
                        'nama' => 'Persentase PTS yang Memiliki Masalah Sengketa Kelembagaan / Legalitas',
                        'satuan' => '%',
                        'tipe_perhitungan' => 'turun_baik',
                        'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                        'unit_kode' => 'POKJA-KLSI',
                        'target_tahunan' => 5.0,
                        'tw1' => 8.0,
                        'tw2' => 7.0,
                        'tw3' => 6.0,
                        'tw4' => 5.0,
                    ],
                ],
            ],
            [
                'id' => 2,
                'kode' => 'SS-02',
                'deskripsi' => 'Meningkatnya Mutu Tata Kelola, Kualifikasi Dosen, dan Prestasi Mahasiswa Perguruan Tinggi Swasta di Wilayah XVI',
                'urutan' => 2,
                'indikator' => [
                    [
                        'id' => 3,
                        'kode' => 'IKU-03',
                        'nama' => 'Persentase Dosen Tetap PTS yang Berkualifikasi S3 / Doktor',
                        'satuan' => '%',
                        'tipe_perhitungan' => 'naik_baik',
                        'unit_nama' => 'Pokja Sumber Daya Perguruan Tinggi',
                        'unit_kode' => 'POKJA-SDPT',
                        'target_tahunan' => 40.0,
                        'tw1' => 30.0,
                        'tw2' => 32.0,
                        'tw3' => 35.0,
                        'tw4' => 40.0,
                    ],
                    [
                        'id' => 4,
                        'kode' => 'IKU-04',
                        'nama' => 'Persentase Mahasiswa PTS yang Berprestasi Tingkat Nasional atau Internasional',
                        'satuan' => '%',
                        'tipe_perhitungan' => 'naik_baik',
                        'unit_nama' => 'Pokja Akademik dan Kemahasiswaan',
                        'unit_kode' => 'POKJA-AK',
                        'target_tahunan' => 15.0,
                        'tw1' => 10.0,
                        'tw2' => 12.0,
                        'tw3' => 13.0,
                        'tw4' => 15.0,
                    ],
                ],
            ],
            [
                'id' => 3,
                'kode' => 'SS-03',
                'deskripsi' => 'Terwujudnya Tata Kelola Lembaga yang Akuntabel, Transparan, dan Berorientasi Pelayanan Prima (Zona Integritas)',
                'urutan' => 3,
                'indikator' => [
                    [
                        'id' => 5,
                        'kode' => 'IKU-05',
                        'nama' => 'Indeks Kepuasan Masyarakat (IKM) atas Pelayanan Lembaga Layanan Pendidikan Tinggi Wilayah XVI',
                        'satuan' => 'Poin',
                        'tipe_perhitungan' => 'naik_baik',
                        'unit_nama' => 'Bagian Umum',
                        'unit_kode' => 'BAG-UMUM',
                        'target_tahunan' => 90.0,
                        'tw1' => 85.0,
                        'tw2' => 86.0,
                        'tw3' => 88.0,
                        'tw4' => 90.0,
                    ],
                ],
            ],
        ];

        $riwayatRenstra = [
            [
                'id' => 2,
                'kode' => 'RENSTRA-2020-2024',
                'nama' => 'Rencana Strategis LLDIKTI Wilayah XVI Tahun 2020-2024',
                'tahun_mulai' => 2020,
                'tahun_selesai' => 2024,
                'is_aktif' => false,
                'total_sasaran' => 3,
                'total_indikator' => 6,
            ],
        ];

        return Inertia::render('Renstra/Index', [
            'renstraAktif' => $renstraAktif,
            'sasaranStrategisList' => $sasaranStrategisList,
            'riwayatRenstra' => $riwayatRenstra,
        ]);
    }
}
