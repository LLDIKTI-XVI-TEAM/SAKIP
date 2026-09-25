<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // 7 Unit-scoped permissions (Koreksi Q32)
            [
                'kode' => 'pengukuran:create',
                'entitas' => 'pengukuran',
                'aksi' => 'create',
                'keterangan' => 'Membuat/mengisi pengukuran capaian indikator pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'pengukuran:update',
                'entitas' => 'pengukuran',
                'aksi' => 'update',
                'keterangan' => 'Mengubah pengukuran capaian indikator pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'rencana_aksi:create',
                'entitas' => 'rencana_aksi',
                'aksi' => 'create',
                'keterangan' => 'Membuat rencana aksi pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'rencana_aksi:update',
                'entitas' => 'rencana_aksi',
                'aksi' => 'update',
                'keterangan' => 'Mengubah rencana aksi pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'rencana_aksi:ajukan',
                'entitas' => 'rencana_aksi',
                'aksi' => 'ajukan',
                'keterangan' => 'Mengajukan rencana aksi pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'kegiatan:create',
                'entitas' => 'kegiatan',
                'aksi' => 'create',
                'keterangan' => 'Membuat kegiatan pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'kegiatan:update',
                'entitas' => 'kegiatan',
                'aksi' => 'update',
                'keterangan' => 'Mengubah kegiatan pada unit tertentu',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],

            // Global permissions (akses, unit, pengukuran, dsb)
            [
                'kode' => 'akses:update',
                'entitas' => 'akses',
                'aksi' => 'update',
                'keterangan' => 'Mengelola hak akses, penetapan peran, dan explicit deny',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'delegasi:update',
                'entitas' => 'delegasi',
                'aksi' => 'update',
                'keterangan' => 'Memberikan atau mencabut izin operasional per unit (Grant Unit)',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'rencana_aksi:read',
                'entitas' => 'rencana_aksi',
                'aksi' => 'read',
                'keterangan' => 'Membaca rencana aksi',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'kegiatan:read',
                'entitas' => 'kegiatan',
                'aksi' => 'read',
                'keterangan' => 'Membaca kegiatan',
                'butuh_scope' => Permission::SCOPE_UNIT,
                'sensitif' => false,
            ],
            [
                'kode' => 'pengguna:read',
                'entitas' => 'pengguna',
                'aksi' => 'read',
                'keterangan' => 'Melihat data pengguna dan evaluasi izin',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'unit:read',
                'entitas' => 'unit',
                'aksi' => 'read',
                'keterangan' => 'Melihat master unit organisasi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'unit:create',
                'entitas' => 'unit',
                'aksi' => 'create',
                'keterangan' => 'Menambah master unit organisasi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'unit:update',
                'entitas' => 'unit',
                'aksi' => 'update',
                'keterangan' => 'Mengubah master unit organisasi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'unit:delete',
                'entitas' => 'unit',
                'aksi' => 'delete',
                'keterangan' => 'Menghapus master unit kosong (Superadmin)',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'pengaturan:update',
                'entitas' => 'pengaturan',
                'aksi' => 'update',
                'keterangan' => 'Mengubah setelan aplikasi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'renstra:read',
                'entitas' => 'renstra',
                'aksi' => 'read',
                'keterangan' => 'Membaca data Renstra',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'renstra:create',
                'entitas' => 'renstra',
                'aksi' => 'create',
                'keterangan' => 'Membuat draft Renstra',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'renstra:update',
                'entitas' => 'renstra',
                'aksi' => 'update',
                'keterangan' => 'Mengubah Renstra',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'renstra:delete',
                'entitas' => 'renstra',
                'aksi' => 'delete',
                'keterangan' => 'Menghapus Renstra',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'pengukuran:verifikasi',
                'entitas' => 'pengukuran',
                'aksi' => 'verifikasi',
                'keterangan' => 'Memverifikasi pengukuran capaian',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'pengukuran:sahkan',
                'entitas' => 'pengukuran',
                'aksi' => 'sahkan',
                'keterangan' => 'Mengesahkan pengukuran capaian',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'pengukuran:buka_kembali',
                'entitas' => 'pengukuran',
                'aksi' => 'buka_kembali',
                'keterangan' => 'Membuka kembali pengukuran yang telah disahkan',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'rencana_aksi:verifikasi',
                'entitas' => 'rencana_aksi',
                'aksi' => 'verifikasi',
                'keterangan' => 'Memverifikasi rencana aksi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'rencana_aksi:sahkan',
                'entitas' => 'rencana_aksi',
                'aksi' => 'sahkan',
                'keterangan' => 'Mengesahkan rencana aksi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'rencana_aksi:buka_kembali',
                'entitas' => 'rencana_aksi',
                'aksi' => 'buka_kembali',
                'keterangan' => 'Membuka kembali rencana aksi',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'kegiatan:delete',
                'entitas' => 'kegiatan',
                'aksi' => 'delete',
                'keterangan' => 'Menghapus kegiatan',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'komponen:read',
                'entitas' => 'komponen',
                'aksi' => 'read',
                'keterangan' => 'Melihat konfigurasi komponen indikator',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'komponen:create',
                'entitas' => 'komponen',
                'aksi' => 'create',
                'keterangan' => 'Menambah komponen indikator',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => false,
            ],
            [
                'kode' => 'komponen:update',
                'entitas' => 'komponen',
                'aksi' => 'update',
                'keterangan' => 'Mengubah komponen indikator',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
            [
                'kode' => 'komponen:delete',
                'entitas' => 'komponen',
                'aksi' => 'delete',
                'keterangan' => 'Menghapus/menonaktifkan komponen indikator',
                'butuh_scope' => Permission::SCOPE_GLOBAL,
                'sensitif' => true,
            ],
        ];

        foreach ($permissions as $data) {
            Permission::updateOrCreate(
                ['kode' => $data['kode']],
                [
                    'entitas' => $data['entitas'],
                    'aksi' => $data['aksi'],
                    'keterangan' => $data['keterangan'],
                    'butuh_scope' => $data['butuh_scope'],
                    'sensitif' => $data['sensitif'],
                ]
            );
        }

        // Rerun tidak mengaktifkan ulang peran/permission atau menimpa izin yang dikelola.
        Role::firstOrCreate(
            ['kode' => 'superadmin'],
            ['nama' => 'Super Admin', 'urutan' => 1, 'is_sistem' => true, 'aktif' => true]
        );
        Role::firstOrCreate(
            ['kode' => 'admin'],
            ['nama' => 'Admin Pengelola', 'urutan' => 2, 'is_sistem' => true, 'aktif' => true]
        );
        Role::firstOrCreate(
            ['kode' => 'perencanaan'],
            ['nama' => 'Perencanaan', 'urutan' => 3, 'is_sistem' => true, 'aktif' => true]
        );
        Role::firstOrCreate(
            ['kode' => 'pimpinan'],
            ['nama' => 'Pimpinan', 'urutan' => 4, 'is_sistem' => true, 'aktif' => true]
        );
        Role::firstOrCreate(
            ['kode' => 'pegawai'],
            ['nama' => 'Pegawai', 'urutan' => 5, 'is_sistem' => true, 'aktif' => true]
        );
    }
}
