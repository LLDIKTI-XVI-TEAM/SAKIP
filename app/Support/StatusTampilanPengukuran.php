<?php

namespace App\Support;

final class StatusTampilanPengukuran
{
    public const BELUM_DIBUKA = 'belum_dibuka';

    public const TIDAK_BERLAKU = 'tidak_berlaku';

    public const BELUM_MENGISI = 'belum_mengisi';

    public const TIDAK_MENGISI = 'tidak_mengisi';

    public const DALAM_PROSES = 'dalam_proses';

    public const BELUM_DAPAT_DIUKUR = 'belum_dapat_diukur';

    public const TANPA_TARGET_PERIODE = 'tanpa_target_periode';

    public const TIDAK_DAPAT_DIHITUNG = 'tidak_dapat_dihitung';

    public const BELUM_DITETAPKAN = 'belum_ditetapkan';

    public const DIHITUNG = [self::BELUM_MENGISI, self::TIDAK_MENGISI, self::DALAM_PROSES, self::BELUM_DAPAT_DIUKUR,
        self::TANPA_TARGET_PERIODE, self::TIDAK_DAPAT_DIHITUNG, self::BELUM_DITETAPKAN];

    /**
     * Status tampilan satu indikator × periode (PRD §23, Q47 §47.3–§47.4). Angka resmi hanya dari isi versi disahkan;
     * Tercapai/Belum tercapai belum ada karena status capaian belum dapat ditetapkan.
     *
     * @param  array<string, mixed>|null  $versiDisahkan  isi `snapshot` versi disahkan, null bila tidak ada
     */
    public static function tentukan(bool $dibuka, bool $berlaku, ?string $statusAlur, bool $lewatJendela, bool $periodeLampau, ?array $versiDisahkan): string
    {
        if (! $dibuka) {
            return self::BELUM_DIBUKA;
        }
        if (! $berlaku) {
            return self::TIDAK_BERLAKU;
        }
        if ($statusAlur === null) {
            return self::BELUM_MENGISI;
        }
        // Periode lampau diisi Perencanaan setelah jendela lewat, jadi draft di sana bukan kelalaian PIC.
        if ($statusAlur === 'draft' && $lewatJendela && ! $periodeLampau) {
            return self::TIDAK_MENGISI;
        }
        if ($statusAlur !== 'disahkan' || $versiDisahkan === null) {
            return self::DALAM_PROSES;
        }

        return match (true) {
            $versiDisahkan['status_perhitungan'] === 'belum_dapat_diukur' => self::BELUM_DAPAT_DIUKUR,
            (bool) ($versiDisahkan['tanpa_target_periode'] ?? false) => self::TANPA_TARGET_PERIODE,
            $versiDisahkan['status_perhitungan'] === 'tidak_dapat_dihitung' => self::TIDAK_DAPAT_DIHITUNG,
            default => self::BELUM_DITETAPKAN,
        };
    }
}
