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

    public const TERCAPAI = 'tercapai';

    public const BELUM_TERCAPAI = 'belum_tercapai';

    public const DIHITUNG = [self::BELUM_MENGISI, self::TIDAK_MENGISI, self::DALAM_PROSES, self::BELUM_DAPAT_DIUKUR,
        self::TANPA_TARGET_PERIODE, self::TIDAK_DAPAT_DIHITUNG, self::BELUM_DITETAPKAN, self::TERCAPAI, self::BELUM_TERCAPAI];

    /**
     * Status tampilan satu indikator × periode (PRD §23, Q47 §47.3–§47.4, Q64 §64.1 butir 4–5). Angka resmi hanya dari
     * isi versi disahkan. Precedence: `belum_dapat_diukur` → `tanpa_target_periode` → `tidak_dapat_dihitung` →
     * Tercapai/Belum tercapai (hanya dari status capaian versi disahkan) → Belum ditetapkan; status capaian tidak pernah
     * menimpa tiga kondisi pertama.
     *
     * @param  array<string, mixed>|null  $versiDisahkan  isi `snapshot` versi disahkan, null bila tidak ada
     * @param  string|null  $statusCapaian  `status_capaian.status` yang berlaku pada versi disahkan itu; null bila belum ditetapkan
     */
    public static function tentukan(bool $dibuka, bool $berlaku, ?string $statusAlur, bool $lewatJendela, bool $periodeLampau, ?array $versiDisahkan, ?string $statusCapaian = null): string
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
            $statusCapaian === self::TERCAPAI => self::TERCAPAI,
            $statusCapaian === self::BELUM_TERCAPAI => self::BELUM_TERCAPAI,
            default => self::BELUM_DITETAPKAN,
        };
    }
}
