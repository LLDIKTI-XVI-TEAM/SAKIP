<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexRencanaAksi extends Controller
{
    public function __invoke(Request $request): Response
    {
        $rencanaAksiList = [
            [
                'id' => 1,
                'nama_rencana_aksi' => 'Bimbingan Teknis & Pendampingan Penyusunan Borang Akreditasi Prodi & Institusi PTS',
                'uraian' => 'Melaksanakan klinik akreditasi terfokus untuk PTS terakreditasi C/Baik menuju Baik Sekali/Unggul dengan menghadirkan asesor BAN-PT/LAM.',
                'tahun' => 2026,
                'indikator_kode' => 'IKU-01',
                'indikator_nama' => 'Persentase PTS yang Terakreditasi Minimal Baik Sekali',
                'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                'unit_kode' => 'POKJA-KLSI',
                'penanggung_jawab_nama' => 'Staf Teknis Kelembagaan',
                'status_alur' => 'disahkan',
                'target_triwulan_1' => 'Identifikasi 12 PTS sasaran & pembentukan tim klinik akreditasi',
                'target_triwulan_2' => 'Pelaksanaan Bimtek Akreditasi Tahap I untuk 15 Program Studi',
                'target_triwulan_3' => 'Review draft borang akreditasi bersama Asesor Eksternal',
                'target_triwulan_4' => 'Monitoring submit instrumen akreditasi ke SAPTO / LAM',
                'disahkan_at' => '2026-01-20 10:30',
                'disahkan_by_nama' => 'Koordinator Perencanaan',
            ],
            [
                'id' => 2,
                'nama_rencana_aksi' => 'Fasilitasi Mediasi Sengketa Yayasan & Rekonsiliasi Legalitas Tata Kelola PTS',
                'uraian' => 'Pendampingan advokasi hukum dan mediasi musyawarah bagi PTS yang mengalami sengketa kepengurusan badan penyelenggara yayasan.',
                'tahun' => 2026,
                'indikator_kode' => 'IKU-02',
                'indikator_nama' => 'Persentase PTS yang Memiliki Masalah Sengketa Kelembagaan / Legalitas',
                'unit_nama' => 'Pokja Kelembagaan dan Sistem Informasi',
                'unit_kode' => 'POKJA-KLSI',
                'penanggung_jawab_nama' => 'Staf Teknis Kelembagaan',
                'status_alur' => 'diajukan',
                'target_triwulan_1' => 'Inventarisasi 5 PTS berstatus sengketa dan telaah berkas legalitas',
                'target_triwulan_2' => 'Rapat koordinasi rekonsiliasi yayasan bersama Ditjen Diktiristek',
                'target_triwulan_3' => 'Penyusunan berita acara kesepakatan damai pengurus yayasan',
                'target_triwulan_4' => 'Pencabutan status pengawasan ketat untuk minimal 3 PTS',
                'disahkan_at' => null,
                'disahkan_by_nama' => null,
            ],
            [
                'id' => 3,
                'nama_rencana_aksi' => 'Sosialisasi Beasiswa Doktoral & Pelatihan Penulisan Jurnal Bereputasi bagi Dosen PTS',
                'uraian' => 'Mendorong percepatan studi lanjut S3 dosen tetap melalui coaching proposal beasiswa BPI/LPDP dan pelatihan publikasi Scopus/SINTA 1-2.',
                'tahun' => 2026,
                'indikator_kode' => 'IKU-03',
                'indikator_nama' => 'Persentase Dosen Tetap PTS yang Berkualifikasi S3 / Doktor',
                'unit_nama' => 'Pokja Sumber Daya Perguruan Tinggi',
                'unit_kode' => 'POKJA-SDPT',
                'penanggung_jawab_nama' => 'Staf Teknis SDPT',
                'status_alur' => 'diverifikasi',
                'target_triwulan_1' => 'Sosialisasi beasiswa BPI/BPP-LN untuk 80 dosen muda PTS',
                'target_triwulan_2' => 'Workshop Academic Writing & Penulisan Proposal Riset Doktoral',
                'target_triwulan_3' => 'Pendampingan administrasi LoA perguruan tinggi tujuan',
                'target_triwulan_4' => 'Monitoring dosen yang resmi memperoleh status tugas belajar S3',
                'disahkan_at' => null,
                'disahkan_by_nama' => null,
            ],
            [
                'id' => 4,
                'nama_rencana_aksi' => 'Fasilitasi Bootcamp Kompetisi Mahasiswa & Monitoring Program MBKM Mandiri',
                'uraian' => 'Pembinaan intensif mahasiswa PTS menghadapi ajang NUDC, KDMI, PKM, LIDM, serta penguatan kemitraan industri untuk magang MBKM.',
                'tahun' => 2026,
                'indikator_kode' => 'IKU-04',
                'indikator_nama' => 'Persentase Mahasiswa PTS yang Berprestasi Tingkat Nasional/Internasional',
                'unit_nama' => 'Pokja Akademik dan Kemahasiswaan',
                'unit_kode' => 'POKJA-AK',
                'penanggung_jawab_nama' => 'Staf Teknis Akademik',
                'status_alur' => 'draft',
                'target_triwulan_1' => 'Seleksi internal wilayah XVI untuk ajang NUDC dan KDMI',
                'target_triwulan_2' => 'Coaching clinic tim lolos seleksi wilayah menuju nasional',
                'target_triwulan_3' => 'Monitoring keikutsertaan kompetisi Puspresnas',
                'target_triwulan_4' => 'Pemberian apresiasi pimpinan kepada mahasiswa dan PTS berprestasi',
                'disahkan_at' => null,
                'disahkan_by_nama' => null,
            ],
            [
                'id' => 5,
                'nama_rencana_aksi' => 'Survei Berkala Kepuasan Layanan & Peningkatan Fasilitas Layanan Terpadu (ULT)',
                'uraian' => 'Modernisasi aplikasi helpdesk, survei kepuasan bulanan, dan audit kepatuhan standar pelayanan publik Zona Integritas.',
                'tahun' => 2026,
                'indikator_kode' => 'IKU-05',
                'indikator_nama' => 'Indeks Kepuasan Masyarakat (IKM) atas Pelayanan LLDIKTI XVI',
                'unit_nama' => 'Bagian Umum',
                'unit_kode' => 'BAG-UMUM',
                'penanggung_jawab_nama' => 'Pengelola TI & Pengolah Data',
                'status_alur' => 'disahkan',
                'target_triwulan_1' => 'Pembaruan kuesioner survei IKM dan integrasi QR Code di ULT',
                'target_triwulan_2' => 'Pelaksanaan survei IKM Semester I (target minimal 250 responden)',
                'target_triwulan_3' => 'Evaluasi tindak lanjut pengaduan & reviu kecepatan respon layanan',
                'target_triwulan_4' => 'Penyusunan laporan akhir IKM LLDIKTI XVI Tahun 2026',
                'disahkan_at' => '2026-01-25 14:15',
                'disahkan_by_nama' => 'Koordinator Perencanaan',
            ],
        ];

        $indikatorOptions = [
            ['id' => 1, 'kode' => 'IKU-01', 'nama' => 'Persentase PTS yang Terakreditasi Minimal Baik Sekali'],
            ['id' => 2, 'kode' => 'IKU-02', 'nama' => 'Persentase PTS yang Memiliki Masalah Sengketa Kelembagaan / Legalitas'],
            ['id' => 3, 'kode' => 'IKU-03', 'nama' => 'Persentase Dosen Tetap PTS yang Berkualifikasi S3 / Doktor'],
            ['id' => 4, 'kode' => 'IKU-04', 'nama' => 'Persentase Mahasiswa PTS yang Berprestasi Tingkat Nasional/Internasional'],
            ['id' => 5, 'kode' => 'IKU-05', 'nama' => 'Indeks Kepuasan Masyarakat (IKM) atas Pelayanan LLDIKTI XVI'],
        ];

        $unitOptions = [
            ['id' => 2, 'kode' => 'BAG-UMUM', 'nama' => 'Bagian Umum'],
            ['id' => 3, 'kode' => 'POKJA-KLSI', 'nama' => 'Pokja Kelembagaan dan Sistem Informasi'],
            ['id' => 4, 'kode' => 'POKJA-AK', 'nama' => 'Pokja Akademik dan Kemahasiswaan'],
            ['id' => 5, 'kode' => 'POKJA-SDPT', 'nama' => 'Pokja Sumber Daya Perguruan Tinggi'],
        ];

        return Inertia::render('RencanaAksi/Index', [
            'rencanaAksiList' => $rencanaAksiList,
            'indikatorOptions' => $indikatorOptions,
            'unitOptions' => $unitOptions,
            'tahunAktif' => 2026,
            'can' => [
                'create' => true,
                'verify' => true,
                'ratify' => true,
            ],
        ]);
    }
}
