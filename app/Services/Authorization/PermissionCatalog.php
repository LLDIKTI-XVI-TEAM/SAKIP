<?php

namespace App\Services\Authorization;

final class PermissionCatalog
{
    public const ACTIONS = [
        'renstra' => ['create', 'read', 'update', 'delete'],
        'sasaran' => ['create', 'update', 'delete'],
        'indikator' => ['create', 'read', 'update', 'delete'],
        'target' => ['update'],
        'pk' => ['create', 'update'],
        'regulasi' => ['create', 'read', 'update', 'delete'],
        'periode' => ['create', 'update'],
        'jadwal' => ['create', 'update', 'aktivasi', 'tutup', 'buka_kembali'],
        'penanggung_jawab' => ['update'],
        'rencana_aksi' => ['read', 'create', 'update', 'ajukan', 'verifikasi', 'kembalikan', 'sahkan', 'buka_kembali'],
        'kegiatan' => ['read', 'create', 'update', 'delete'],
        'komponen' => ['create', 'read', 'update', 'delete'],
        'jenis_berkas' => ['create', 'read', 'update', 'delete'],
        'berkas' => ['read', 'upload', 'delete'],
        'pengukuran' => ['create', 'update', 'read', 'verifikasi', 'kembalikan', 'sahkan', 'buka_kembali', 'setujui'],
        'status_capaian' => ['update'],
        'rekomendasi' => ['tetapkan'],
        'unit' => ['create', 'read', 'update', 'delete'],
        'pengguna' => ['read'],
        'akses' => ['update'],
        'delegasi' => ['update'],
        'dashboard' => ['read'],
        'laporan' => ['read', 'ekspor'],
        'audit' => ['read'],
        'pengaturan' => ['update'],
    ];

    public const UNIT_SCOPED = ['pengukuran:create', 'pengukuran:update', 'rencana_aksi:create', 'rencana_aksi:update', 'rencana_aksi:ajukan', 'kegiatan:create', 'kegiatan:update'];

    /** Tepat 7 permission yang sah diberikan melalui form/endpoint Grant Unit (ISS-01.04 / Q32) */
    public const GRANTABLE_UNIT_PERMISSIONS = [
        'pengukuran:create',
        'pengukuran:update',
        'rencana_aksi:create',
        'rencana_aksi:update',
        'rencana_aksi:ajukan',
        'kegiatan:create',
        'kegiatan:update',
    ];

    public const SENSITIVE = ['pengukuran:sahkan', 'pengukuran:buka_kembali', 'pengukuran:verifikasi', 'rencana_aksi:verifikasi', 'rencana_aksi:sahkan', 'rencana_aksi:buka_kembali', 'jadwal:aktivasi', 'jadwal:tutup', 'jadwal:buka_kembali', 'status_capaian:update', 'rekomendasi:tetapkan', 'komponen:update', 'komponen:delete', 'jenis_berkas:update', 'jenis_berkas:delete', 'regulasi:update', 'regulasi:delete', 'akses:update', 'pengaturan:update', 'berkas:delete', 'kegiatan:delete', 'unit:delete'];

    public const DESCRIPTION_OVERRIDES = [
        'akses:update' => 'Menetapkan peran pengguna dan mengelola pembatasan izin eksplisit.',
        'delegasi:update' => 'Memberikan dan mencabut grant izin tambahan per unit.',
        'rencana_aksi:read' => 'Membaca rencana aksi sesuai izin efektif dan aturan akses data.',
        'kegiatan:read' => 'Membaca kegiatan sesuai izin efektif dan aturan akses data.',
    ];

    public const DEFAULT_DESCRIPTIONS = [
        'pengukuran:create' => 'Membuat/mengisi pengukuran capaian indikator pada unit tertentu',
        'pengukuran:update' => 'Mengubah pengukuran capaian indikator pada unit tertentu',
        'rencana_aksi:create' => 'Membuat rencana aksi pada unit tertentu',
        'rencana_aksi:update' => 'Mengubah rencana aksi pada unit tertentu',
        'rencana_aksi:ajukan' => 'Mengajukan rencana aksi pada unit tertentu',
        'kegiatan:create' => 'Membuat kegiatan pada unit tertentu',
        'kegiatan:update' => 'Mengubah kegiatan pada unit tertentu',
        'pengguna:read' => 'Melihat data pengguna dan evaluasi izin',
        'unit:read' => 'Melihat master unit organisasi',
        'unit:create' => 'Menambah master unit organisasi',
        'unit:update' => 'Mengubah master unit organisasi',
        'unit:delete' => 'Menghapus master unit kosong (Superadmin)',
        'pengaturan:update' => 'Mengubah setelan aplikasi',
        'renstra:read' => 'Membaca data Renstra',
        'renstra:create' => 'Membuat draft Renstra',
        'renstra:update' => 'Mengubah Renstra',
        'renstra:delete' => 'Menghapus Renstra',
        'pengukuran:verifikasi' => 'Memverifikasi pengukuran capaian',
        'pengukuran:sahkan' => 'Mengesahkan pengukuran capaian',
        'pengukuran:buka_kembali' => 'Membuka kembali pengukuran yang telah disahkan',
        'rencana_aksi:verifikasi' => 'Memverifikasi rencana aksi',
        'rencana_aksi:sahkan' => 'Mengesahkan rencana aksi',
        'rencana_aksi:buka_kembali' => 'Membuka kembali rencana aksi',
        'kegiatan:delete' => 'Menghapus kegiatan',
        'komponen:read' => 'Melihat konfigurasi komponen indikator',
        'komponen:create' => 'Menambah komponen indikator',
        'komponen:update' => 'Mengubah komponen indikator',
        'komponen:delete' => 'Menghapus/menonaktifkan komponen indikator',
    ];

    /** Katalog kode tunggal; perubahan kode dilakukan melalui rilis dan seeder. @return list<string> */
    public static function codes(): array
    {
        $codes = [];
        foreach (self::ACTIONS as $entity => $actions) {
            foreach ($actions as $action) {
                $codes[] = $entity.':'.$action;
            }
        }

        return $codes;
    }
}
