<?php

namespace App\Support;

use App\Services\Authorization\PermissionCatalog;

final class PermissionCodes
{
    // --- 15 Kode Permission Utama dalam Scope 10 Issue Aktif (§5 Dokumen Konfirmasi Permission) ---

    // Pengguna & Hak Akses
    public const PENGGUNA_READ = 'pengguna:read';

    public const AKSES_UPDATE = 'akses:update';

    public const DELEGASI_UPDATE = 'delegasi:update';

    // Master Unit Organisasi
    public const UNIT_CREATE = 'unit:create';

    public const UNIT_READ = 'unit:read';

    public const UNIT_UPDATE = 'unit:update';

    public const UNIT_DELETE = 'unit:delete';

    // Dasar Aturan / Regulasi
    public const REGULASI_CREATE = 'regulasi:create';

    public const REGULASI_READ = 'regulasi:read';

    public const REGULASI_UPDATE = 'regulasi:update';

    public const REGULASI_DELETE = 'regulasi:delete';

    // Master Renstra
    public const RENSTRA_CREATE = 'renstra:create';

    public const RENSTRA_READ = 'renstra:read';

    public const RENSTRA_UPDATE = 'renstra:update';

    public const RENSTRA_DELETE = 'renstra:delete';

    // Perjanjian Kinerja (PK)
    public const PK_CREATE = 'pk:create';

    public const PK_UPDATE = 'pk:update';

    public const PERIODE_CREATE = 'periode:create';

    public const PERIODE_UPDATE = 'periode:update';

    public const JADWAL_CREATE = 'jadwal:create';

    public const JADWAL_UPDATE = 'jadwal:update';

    public const JADWAL_AKTIVASI = 'jadwal:aktivasi';

    // Pengaturan Presentasional & Storage
    public const PENGATURAN_UPDATE = 'pengaturan:update';

    // Persyaratan Jenis Berkas
    public const JENIS_BERKAS_CREATE = 'jenis_berkas:create';

    public const JENIS_BERKAS_READ = 'jenis_berkas:read';

    public const JENIS_BERKAS_UPDATE = 'jenis_berkas:update';

    public const JENIS_BERKAS_DELETE = 'jenis_berkas:delete';

    // Sasaran Strategis & Indikator Kinerja
    public const SASARAN_CREATE = 'sasaran:create';

    public const SASARAN_UPDATE = 'sasaran:update';

    public const SASARAN_DELETE = 'sasaran:delete';

    public const INDIKATOR_CREATE = 'indikator:create';

    public const INDIKATOR_READ = 'indikator:read';

    public const TARGET_UPDATE = 'target:update';

    public const INDIKATOR_UPDATE = 'indikator:update';

    public const INDIKATOR_DELETE = 'indikator:delete';

    // --- Kode Permission Scope Unit ---

    public const RENCANA_AKSI_READ = 'rencana_aksi:read';

    public const RENCANA_AKSI_CREATE = 'rencana_aksi:create';

    public const RENCANA_AKSI_UPDATE = 'rencana_aksi:update';

    public const RENCANA_AKSI_AJUKAN = 'rencana_aksi:ajukan';

    public const RENCANA_AKSI_SAHKAN = 'rencana_aksi:sahkan';

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
     * 7 Kode Permission Scope Unit untuk Form Grant (§6).
     *
     * @return list<string>
     */
    public static function unitScoped(): array
    {
        return PermissionCatalog::UNIT_SCOPED;
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
    public static function renstra(): array
    {
        return [
            self::RENSTRA_CREATE,
            self::RENSTRA_READ,
            self::RENSTRA_UPDATE,
            self::RENSTRA_DELETE,
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
        return array_values(array_filter(self::unitScoped(), fn (string $code) => str_starts_with($code, 'rencana_aksi:')));
    }

    /** @return list<string> */
    public static function kegiatanUnitScoped(): array
    {
        return array_values(array_filter(self::unitScoped(), fn (string $code) => str_starts_with($code, 'kegiatan:')));
    }

    /** @return list<string> */
    public static function pengukuranUnitScoped(): array
    {
        return array_values(array_filter(self::unitScoped(), fn (string $code) => str_starts_with($code, 'pengukuran:')));
    }

    /** @return list<string> */
    public static function sasaran(): array
    {
        return [
            self::SASARAN_CREATE,
            self::SASARAN_UPDATE,
            self::SASARAN_DELETE,
        ];
    }

    /** @return list<string> */
    public static function indikator(): array
    {
        return [
            self::INDIKATOR_CREATE,
            self::INDIKATOR_READ,
            self::INDIKATOR_UPDATE,
            self::INDIKATOR_DELETE,
        ];
    }
}
