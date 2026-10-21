<?php

namespace App\Services\RencanaAksi;

use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Penentu dua aturan periode: status periode lampau jadwal 2026
 * (`isPeriodeLampau`), dasar pengecualian data awal pada gerbang pengukuran,
 * anggaran kegiatan, dan perencanaan kegiatan; serta pengecualian target
 * periode kosong (`bolehTanpaTarget`), yaitu periode lampau tersebut atau
 * periode sebelum nilai akhir pada indikator yang snapshot-nya dinilai tahunan.
 *
 * Dipisah dari Action karena aturan yang sama dipakai ulang oleh gerbang
 * pengajuan RA (`KesiapanPengajuan`, termasuk perencanaan kegiatan), gerbang
 * review RA (`GerbangReviewRencanaAksi`), prasyarat pengukuran
 * (`SubmissionPrerequisites`), anggaran kegiatan (`AnggaranKegiatan`), preview
 * target (`PreviewTargetPeriode`), dan props RA `boleh_tanpa_target`
 * (`IndexRencanaAksi`), sehingga semua jalur menilai periode dengan cara sama.
 *
 * Murni membaca model yang dipasok caller: tidak ada query tambahan bila relasi
 * `jadwal` dan `periode` sudah dimuat caller, serta tanpa lock, tulis, atau
 * audit. Transaksi dan locking milik Action pemanggil.
 */
class PeriodeTanpaTarget
{
    /**
     * Pengecualian Q46 hanya untuk jadwal 2026. Periode lampau bila
     * `pengisian_selesai` yang berlaku (termasuk hasil revisi resmi) jatuh sebelum
     * tanggal bisnis aktivasi (WITA), sehingga revisi yang menggeser batas
     * melewati tanggal aktivasi menjadikannya periode normal.
     */
    public static function isPeriodeLampau(JadwalTahunan $jadwal, ?PeriodeJadwal $periode): bool
    {
        if ($jadwal->tahun !== 2026 || ! $jadwal->activated_at || ! $periode || $periode->jadwal_id !== $jadwal->id) {
            return false;
        }
        $tanggalAktivasi = CarbonImmutable::parse($jadwal->activated_at, 'UTC')->setTimezone('Asia/Makassar')->toDateString();

        return $periode->pengisian_selesai->toDateString() < $tanggalAktivasi;
    }

    /** @param Collection<int, PeriodeJadwal> $jendela */
    public static function bolehTanpaTarget(JadwalSnapshot $snapshot, PeriodeJadwal $periode, Collection $jendela): bool
    {
        if ($periode->jadwal_id !== $snapshot->jadwal_id) {
            return false;
        }
        if (self::isPeriodeLampau($snapshot->jadwal, $periode)) {
            return true;
        }
        $final = $jendela->first(fn (PeriodeJadwal $window): bool => $window->jadwal_id === $snapshot->jadwal_id && (bool) $window->periode?->is_nilai_akhir);

        return $snapshot->dinilai_tahunan && $periode->periode && $final?->periode
            && $periode->periode->urutan < $final->periode->urutan;
    }
}
