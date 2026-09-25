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

    public const UNIT_SCOPED = [
        'pengukuran:create',
        'pengukuran:update',
        'rencana_aksi:create',
        'rencana_aksi:update',
        'rencana_aksi:ajukan',
        'kegiatan:create',
        'kegiatan:update',
    ];

    public const SENSITIVE = ['pengukuran:sahkan', 'pengukuran:buka_kembali', 'pengukuran:verifikasi', 'rencana_aksi:verifikasi', 'rencana_aksi:sahkan', 'rencana_aksi:buka_kembali', 'jadwal:aktivasi', 'jadwal:tutup', 'jadwal:buka_kembali', 'status_capaian:update', 'rekomendasi:tetapkan', 'komponen:update', 'komponen:delete', 'jenis_berkas:update', 'jenis_berkas:delete', 'regulasi:update', 'regulasi:delete', 'akses:update', 'delegasi:update', 'pengaturan:update', 'berkas:delete', 'kegiatan:delete', 'unit:delete'];

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
