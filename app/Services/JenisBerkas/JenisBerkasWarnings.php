<?php

namespace App\Services\JenisBerkas;

use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Pengaturan;

class JenisBerkasWarnings
{
    public function forFormatChange(JenisBerkas $jb, ?string $newFormatStr): ?string
    {
        $effectiveNewFormatStr = $newFormatStr;
        if ($effectiveNewFormatStr === null || trim($effectiveNewFormatStr) === '') {
            $effectiveNewFormatStr = Pengaturan::where('kunci', 'berkas.format_diizinkan')->value('nilai') ?? 'pdf,docx,xlsx,jpg,jpeg,png';
        }

        $newFormats = array_filter(array_map('trim', explode(',', strtolower($effectiveNewFormatStr))));
        if (empty($newFormats)) {
            return null;
        }

        $oldFormatStr = $jb->format_diizinkan;
        if ($oldFormatStr === null || trim($oldFormatStr) === '') {
            $oldFormatStr = Pengaturan::where('kunci', 'berkas.format_diizinkan')->value('nilai') ?? 'pdf,docx,xlsx,jpg,jpeg,png';
        }

        $oldFormats = array_filter(array_map('trim', explode(',', strtolower($oldFormatStr))));
        if (empty($oldFormats)) {
            return null;
        }

        // Himpunan format lama yang dihilangkan pada konfigurasi baru
        $removedFormats = array_diff($oldFormats, $newFormats);
        if (empty($removedFormats)) {
            return null;
        }

        $existingFiles = BuktiDukung::where('jenis_berkas_id', $jb->id)
            ->where('mode', 'file')
            ->whereNull('dihapus_pada')
            ->get(['nama_asli', 'path']);

        foreach ($existingFiles as $file) {
            $filename = (string) ($file->nama_asli ?? $file->path ?? '');
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext !== '' && in_array($ext, $removedFormats, true)) {
                return 'Peringatan: Format diizinkan dipersempit dan terdapat berkas bukti dukung lama yang formatnya tidak lagi tercakup dalam daftar baru. Bukti lama tetap sah (grandfathered), batas baru hanya berlaku untuk unggahan berikutnya.';
            }
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function forUploadState(bool $isUnggahanAktif, array $data): ?string
    {
        if ($isUnggahanAktif) {
            return null;
        }

        $wajib = $data['wajib'] ?? false;
        $izinkanFile = $data['izinkan_file'] ?? false;
        $izinkanTautan = $data['izinkan_tautan'] ?? false;
        $izinkanTeks = $data['izinkan_teks'] ?? false;
        $semuaModeWajib = $data['semua_mode_wajib'] ?? false;

        $isFileOnlyWajib = $wajib && $izinkanFile && ! $izinkanTautan && ! $izinkanTeks;
        $isSemuaModeWajibWithFile = $wajib && $semuaModeWajib && $izinkanFile;

        if ($isFileOnlyWajib) {
            return 'Peringatan: Mode unggahan file sedang dinonaktifkan pada setelan aplikasi (berkas.unggahan_aktif = false). Persyaratan wajib ini berpotensi tidak dapat dipenuhi PIC atau ditandai tidak dapat dipenuhi.';
        } elseif ($isSemuaModeWajibWithFile) {
            return 'Peringatan: Mode unggahan file sedang dinonaktifkan pada setelan aplikasi (berkas.unggahan_aktif = false). Persyaratan "semua mode wajib" ini akan mengecualikan kewajiban file (waiver) saat evaluasi bukti.';
        }

        return null;
    }
}
