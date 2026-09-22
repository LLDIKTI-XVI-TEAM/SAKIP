<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IndexRencanaAksi extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        if (! $actor || ! $actor->is_active) {
            abort(403);
        }

        $permission = Permission::where('kode', 'rencana_aksi:read')->where('aktif', true)->first();
        if (! $permission) {
            abort(403);
        }

        // Global deny check: jika user memiliki deny tanpa unit_id, tolak secara global
        $hasGlobalDeny = DB::table('user_permission_denied')
            ->where('user_id', $actor->id)
            ->where('permission_id', $permission->id)
            ->whereNull('unit_id')
            ->exists();

        if ($hasGlobalDeny) {
            abort(403, 'Akses rencana aksi ditolak secara global.');
        }

        // Cek apakah peran user memberikan hak rencana_aksi:read
        $hasGlobalRole = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->join('role_permissions', 'role_permissions.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $actor->id)
            ->where('roles.aktif', true)
            ->where('role_permissions.permission_id', $permission->id)
            ->exists();

        // Unit yang di-deny secara spesifik untuk user ini
        $deniedUnitIds = DB::table('user_permission_denied')
            ->where('user_id', $actor->id)
            ->where('permission_id', $permission->id)
            ->whereNotNull('unit_id')
            ->pluck('unit_id')
            ->all();

        // Unit yang di-grant secara spesifik untuk user ini
        $grantedUnitIds = DB::table('user_permission_granted')
            ->where('user_id', $actor->id)
            ->where('permission_id', $permission->id)
            ->whereNotNull('unit_id')
            ->pluck('unit_id')
            ->all();

        // Unit efektif hasil grant dikurangi deny
        $effectiveGrantedUnitIds = array_values(array_diff($grantedUnitIds, $deniedUnitIds));

        // Otorisasi: user wajib memiliki peran global atau minimal satu grant unit efektif
        if (! $hasGlobalRole && empty($effectiveGrantedUnitIds)) {
            abort(403, 'Anda tidak memiliki hak akses rencana aksi pada unit manapun.');
        }

        $deniedUnitNames = ! empty($deniedUnitIds)
            ? Unit::whereIn('id', $deniedUnitIds)->pluck('nama')->all()
            : [];

        $allowedUnitNames = ! empty($effectiveGrantedUnitIds)
            ? Unit::whereIn('id', $effectiveGrantedUnitIds)->pluck('nama')->all()
            : [];

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

        $unitCodeMap = [
            'Bagian Umum' => 'BAG-UMUM',
            'Pokja Kelembagaan dan Sistem Informasi' => 'POKJA-KLSI',
            'Pokja Akademik dan Kemahasiswaan' => 'POKJA-AK',
            'Pokja Sumber Daya Perguruan Tinggi' => 'POKJA-SDPT',
        ];

        $resolveUnitCode = function (?string $nama) use ($unitCodeMap): string {
            if (! $nama) {
                return '-';
            }

            return $unitCodeMap[$nama] ?? Str::slug($nama);
        };

        // Prioritaskan data riil database jika ada
        if (RencanaAksi::exists()) {
            $rencanaAksiList = RencanaAksi::with(['indikator', 'unit', 'penanggungJawab', 'disahkanBy'])
                ->get()
                ->map(function (RencanaAksi $ra) use ($resolveUnitCode) {
                    return [
                        'id' => $ra->id,
                        'nama_rencana_aksi' => $ra->uraian ?? '-',
                        'uraian' => $ra->uraian ?? '',
                        'tahun' => $ra->tahun,
                        'indikator_kode' => $ra->indikator?->kode ?? '-',
                        'indikator_nama' => $ra->indikator?->nama ?? '-',
                        'unit_nama' => $ra->unit?->nama ?? '-',
                        'unit_kode' => $ra->unit ? $resolveUnitCode($ra->unit->nama) : '-',
                        'unit_id' => $ra->unit_id,
                        'penanggung_jawab_nama' => $ra->penanggungJawab?->nama ?? '-',
                        'status_alur' => $ra->status_alur,
                        'target_triwulan_1' => '-',
                        'target_triwulan_2' => '-',
                        'target_triwulan_3' => '-',
                        'target_triwulan_4' => '-',
                        'disahkan_at' => $ra->disahkan_at?->format('Y-m-d H:i'),
                        'disahkan_by_nama' => $ra->disahkanBy?->nama,
                    ];
                })->all();
        }

        // Gabungkan unit database aktif ke opsi jika ada
        $dbUnits = Unit::where('status', 'aktif')->get();
        if ($dbUnits->isNotEmpty()) {
            foreach ($dbUnits as $dbUnit) {
                $code = $resolveUnitCode($dbUnit->nama);
                $matched = false;
                foreach ($unitOptions as &$opt) {
                    if ($opt['nama'] === $dbUnit->nama) {
                        $opt['id'] = $dbUnit->id;
                        $opt['kode'] = $code;
                        $matched = true;
                        break;
                    }
                }
                unset($opt);

                if (! $matched) {
                    $unitOptions[] = [
                        'id' => $dbUnit->id,
                        'kode' => $code,
                        'nama' => $dbUnit->nama,
                    ];
                }
            }
        }

        // Saring rencanaAksiList berdasarkan hak unit efektif
        $rencanaAksiList = array_values(array_filter($rencanaAksiList, function ($item) use ($hasGlobalRole, $deniedUnitNames, $allowedUnitNames, $deniedUnitIds, $effectiveGrantedUnitIds) {
            $unitId = $item['unit_id'] ?? null;
            $unitNama = $item['unit_nama'] ?? '';

            if ($hasGlobalRole) {
                if ($unitId !== null) {
                    return ! in_array($unitId, $deniedUnitIds, true);
                }
                if ($unitNama !== '') {
                    return ! in_array($unitNama, $deniedUnitNames, true);
                }

                return true;
            }

            // Akses berbasis grant: gunakan UUID jika tersedia, fallback nama hanya untuk data mock
            if ($unitId !== null) {
                return in_array($unitId, $effectiveGrantedUnitIds, true);
            }
            if ($unitNama !== '') {
                return in_array($unitNama, $allowedUnitNames, true);
            }

            return false;
        }));

        // Saring unitOptions berdasarkan hak unit efektif
        $unitOptions = array_values(array_filter($unitOptions, function ($unit) use ($hasGlobalRole, $deniedUnitNames, $allowedUnitNames, $deniedUnitIds, $effectiveGrantedUnitIds) {
            $unitId = $unit['id'] ?? null;
            $unitNama = $unit['nama'] ?? '';

            if ($hasGlobalRole) {
                if ($unitId !== null && is_string($unitId) && Str::isUuid($unitId)) {
                    return ! in_array($unitId, $deniedUnitIds, true);
                }
                if ($unitNama !== '') {
                    return ! in_array($unitNama, $deniedUnitNames, true);
                }

                return true;
            }

            if ($unitId !== null && is_string($unitId) && Str::isUuid($unitId)) {
                return in_array($unitId, $effectiveGrantedUnitIds, true);
            }
            if ($unitNama !== '') {
                return in_array($unitNama, $allowedUnitNames, true);
            }

            return false;
        }));

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
