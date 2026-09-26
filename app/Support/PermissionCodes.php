<?php

namespace App\Support;

final class PermissionCodes
{
    // --- 15 Kode Permission Utama dalam Scope 10 Issue Aktif (§5 Dokumen Konfirmasi Permission) ---

    // Pengguna & Hak Akses (ISS-01.03, ISS-01.04, ISS-01.05)
    public const PENGGUNA_READ = 'pengguna:read';

    public const AKSES_UPDATE = 'akses:update';

    // Master Unit Organisasi (ISS-01.02)
    public const UNIT_CREATE = 'unit:create';

    public const UNIT_READ = 'unit:read';

    public const UNIT_UPDATE = 'unit:update';

    public const UNIT_DELETE = 'unit:delete';

    // Dasar Aturan / Regulasi (ISS-02.01)
    public const REGULASI_CREATE = 'regulasi:create';

    public const REGULASI_READ = 'regulasi:read';

    public const REGULASI_UPDATE = 'regulasi:update';

    public const REGULASI_DELETE = 'regulasi:delete';

    // Pengaturan Presentasional & Storage (ISS-13.01, ISS-13.02)
    public const PENGATURAN_UPDATE = 'pengaturan:update';

    // Persyaratan Jenis Berkas (ISS-11.01)
    public const JENIS_BERKAS_CREATE = 'jenis_berkas:create';

    public const JENIS_BERKAS_READ = 'jenis_berkas:read';

    public const JENIS_BERKAS_UPDATE = 'jenis_berkas:update';

    public const JENIS_BERKAS_DELETE = 'jenis_berkas:delete';

    // --- 9 Kode Permission Scope Unit untuk Form Grant (ISS-01.04, §6 Dokumen Konfirmasi Permission) ---

    public const RENCANA_AKSI_READ = 'rencana_aksi:read';

    public const RENCANA_AKSI_CREATE = 'rencana_aksi:create';

    public const RENCANA_AKSI_UPDATE = 'rencana_aksi:update';

    public const RENCANA_AKSI_AJUKAN = 'rencana_aksi:ajukan';

    public const KEGIATAN_READ = 'kegiatan:read';

    public const KEGIATAN_CREATE = 'kegiatan:create';

    public const KEGIATAN_UPDATE = 'kegiatan:update';

    public const PENGUKURAN_CREATE = 'pengukuran:create';

    public const PENGUKURAN_UPDATE = 'pengukuran:update';

    // --- Berkas / Lampiran Pendukung ---

    public const BERKAS_READ = 'berkas:read';

    public const BERKAS_UPLOAD = 'berkas:upload';

    public const BERKAS_DELETE = 'berkas:delete';

    // --- Izin Umum Tambahan Baseline ---

    public const DASHBOARD_READ = 'dashboard:read';

    public const AUDIT_READ = 'audit:read';

    public const KOMPONEN_READ = 'komponen:read';

    public const LAPORAN_READ = 'laporan:read';

    public const LAPORAN_EKSPOR = 'laporan:ekspor';

    /**
     * 15 Kode Permission Utama dalam Scope 10 Issue Aktif (§5)
     *
     * @return list<string>
     */
    public static function activeIssueCodes(): array
    {
        return [
            self::PENGGUNA_READ,
            self::AKSES_UPDATE,
            self::UNIT_CREATE,
            self::UNIT_READ,
            self::UNIT_UPDATE,
            self::UNIT_DELETE,
            self::REGULASI_CREATE,
            self::REGULASI_READ,
            self::REGULASI_UPDATE,
            self::REGULASI_DELETE,
            self::PENGATURAN_UPDATE,
            self::JENIS_BERKAS_CREATE,
            self::JENIS_BERKAS_READ,
            self::JENIS_BERKAS_UPDATE,
            self::JENIS_BERKAS_DELETE,
        ];
    }

    /**
     * 7 Kode Permission Scope Unit untuk Form Grant (§6, ISS-01.04, Q32)
     *
     * @return list<string>
     */
    public static function unitScoped(): array
    {
        return [
            self::RENCANA_AKSI_CREATE,
            self::RENCANA_AKSI_UPDATE,
            self::RENCANA_AKSI_AJUKAN,
            self::KEGIATAN_CREATE,
            self::KEGIATAN_UPDATE,
            self::PENGUKURAN_CREATE,
            self::PENGUKURAN_UPDATE,
        ];
    }

    /**
     * 7 Permission Sensitif dalam Scope 10 Issue Aktif (§8.5)
     *
     * @return list<string>
     */
    public static function sensitiveActive(): array
    {
        return [
            self::AKSES_UPDATE,
            self::UNIT_DELETE,
            self::REGULASI_UPDATE,
            self::REGULASI_DELETE,
            self::PENGATURAN_UPDATE,
            self::JENIS_BERKAS_UPDATE,
            self::JENIS_BERKAS_DELETE,
        ];
    }

    /** @return list<string> */
    public static function unit(): array
    {
        return [
            self::UNIT_CREATE,
            self::UNIT_READ,
            self::UNIT_UPDATE,
            self::UNIT_DELETE,
        ];
    }

    /** @return list<string> */
    public static function regulasi(): array
    {
        return [
            self::REGULASI_CREATE,
            self::REGULASI_READ,
            self::REGULASI_UPDATE,
            self::REGULASI_DELETE,
        ];
    }

    /** @return list<string> */
    public static function jenisBerkas(): array
    {
        return [
            self::JENIS_BERKAS_CREATE,
            self::JENIS_BERKAS_READ,
            self::JENIS_BERKAS_UPDATE,
            self::JENIS_BERKAS_DELETE,
        ];
    }

    /** @return list<string> */
    public static function berkas(): array
    {
        return [
            self::BERKAS_READ,
            self::BERKAS_UPLOAD,
            self::BERKAS_DELETE,
        ];
    }

    /** @return list<string> */
    public static function rencanaAksiUnitScoped(): array
    {
        return [
            self::RENCANA_AKSI_READ,
            self::RENCANA_AKSI_CREATE,
            self::RENCANA_AKSI_UPDATE,
            self::RENCANA_AKSI_AJUKAN,
        ];
    }

    /** @return list<string> */
    public static function kegiatanUnitScoped(): array
    {
        return [
            self::KEGIATAN_READ,
            self::KEGIATAN_CREATE,
            self::KEGIATAN_UPDATE,
        ];
    }

    /** @return list<string> */
    public static function pengukuranUnitScoped(): array
    {
        return [
            self::PENGUKURAN_CREATE,
            self::PENGUKURAN_UPDATE,
        ];
    }
}
