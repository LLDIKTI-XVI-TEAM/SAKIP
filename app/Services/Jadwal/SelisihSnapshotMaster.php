<?php

namespace App\Services\Jadwal;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\TargetKinerja;
use App\Support\PermissionCodes;

/**
 * Selisih konteks versi snapshot terhadap data master yang akan disalin bila koreksi dijalankan (Q79 §79.1).
 *
 * Dipakai bersama oleh KoreksiSnapshot (penolakan "tidak ada selisih" dan izin per kelompok kolom) dan
 * ShowSnapshotIndikator (tampilan selisih), agar keduanya menilai kolom yang sama. Service hanya membaca model yang
 * sudah dimuat caller; `$indikator->komponen` harus berisi komponen aktif terurut seperti salinan snapshot.
 */
class SelisihSnapshotMaster
{
    /** @var list<string> */
    public const IZIN = [PermissionCodes::TARGET_UPDATE, PermissionCodes::INDIKATOR_UPDATE, PermissionCodes::KOMPONEN_UPDATE];

    /** Kolom snapshot beserta label tampilan dan izin kelompoknya (Q79 §79.1 butir 5); urutan menentukan urutan tampilan. */
    public const KOLOM = [
        'nama' => ['Nama indikator', PermissionCodes::INDIKATOR_UPDATE],
        'definisi' => ['Definisi operasional', PermissionCodes::INDIKATOR_UPDATE],
        'satuan' => ['Satuan', PermissionCodes::INDIKATOR_UPDATE],
        'unit_id' => ['Unit penanggung jawab', PermissionCodes::INDIKATOR_UPDATE],
        'arah' => ['Arah penilaian', PermissionCodes::INDIKATOR_UPDATE],
        'presisi' => ['Presisi', PermissionCodes::INDIKATOR_UPDATE],
        'desimal_tampilan' => ['Desimal tampilan', PermissionCodes::INDIKATOR_UPDATE],
        'dinilai_tahunan' => ['Dinilai tahunan', PermissionCodes::INDIKATOR_UPDATE],
        'tipe_perhitungan' => ['Tipe perhitungan', PermissionCodes::KOMPONEN_UPDATE],
        'target' => ['Target PK', PermissionCodes::TARGET_UPDATE],
        'baseline' => ['Baseline', PermissionCodes::TARGET_UPDATE],
    ];

    /**
     * Desimal dibandingkan dalam bentuk ternormalisasi karena skala kolom master dan snapshot dapat berbeda.
     *
     * @return array{kolom: list<array{kolom: string, label: string, lama: string|int|bool|null, baru: string|int|bool|null}>,
     *     komponen: ?array{lama: list<array<string, string|int>>, baru: list<array<string, string|int>>}, izin: list<string>}
     */
    public function hitung(JadwalSnapshot $snapshot, IndikatorKinerja $indikator, ?TargetKinerja $target): array
    {
        $lama = [...$snapshot->only(['nama', 'definisi', 'satuan', 'unit_id', 'arah', 'tipe_perhitungan']),
            'presisi' => (int) $snapshot->presisi, 'desimal_tampilan' => (int) $snapshot->desimal_tampilan, 'dinilai_tahunan' => (bool) $snapshot->dinilai_tahunan,
            'target' => self::desimal($snapshot->getRawOriginal('target')), 'baseline' => self::desimal($snapshot->getRawOriginal('baseline'))];
        $baru = [...$indikator->only(['nama', 'satuan', 'unit_id', 'arah', 'tipe_perhitungan']), 'definisi' => $indikator->definisi_operasional,
            'presisi' => (int) $indikator->presisi, 'desimal_tampilan' => (int) $indikator->desimal_tampilan, 'dinilai_tahunan' => (bool) $indikator->dinilai_tahunan,
            'target' => self::desimal($target?->getRawOriginal('target_tahunan')), 'baseline' => self::desimal($target?->getRawOriginal('baseline'))];

        $kolom = [];
        $izin = [];
        foreach (self::KOLOM as $nama => [$label, $kode]) {
            if ($lama[$nama] !== $baru[$nama]) {
                $kolom[] = ['kolom' => $nama, 'label' => $label, 'lama' => $lama[$nama], 'baru' => $baru[$nama]];
                $izin[] = $kode;
            }
        }
        $komponenLama = $snapshot->komponen->map(fn (JadwalSnapshotKomponen $row): array => self::komponen($row))->values()->all();
        $komponenBaru = $indikator->komponen->map(fn (IndikatorKomponen $row): array => self::komponen($row))->values()->all();
        $komponen = $komponenLama === $komponenBaru ? null : ['lama' => $komponenLama, 'baru' => $komponenBaru];
        if ($komponen !== null) {
            $izin[] = PermissionCodes::KOMPONEN_UPDATE;
        }

        return ['kolom' => $kolom, 'komponen' => $komponen, 'izin' => array_values(array_unique($izin))];
    }

    /** Nilai numeric PostgreSQL tanpa nol di belakang koma; `null` tetap `null`. */
    public static function desimal(?string $nilai): ?string
    {
        if ($nilai === null || ! str_contains($nilai, '.')) {
            return $nilai;
        }

        return rtrim(rtrim($nilai, '0'), '.');
    }

    /** @return array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int} */
    private static function komponen(IndikatorKomponen|JadwalSnapshotKomponen $row): array
    {
        $id = $row instanceof JadwalSnapshotKomponen ? $row->komponen_id : $row->id;

        return ['komponen_id' => (string) $id, 'kode' => (string) $row->kode, 'label' => (string) $row->label, 'peran' => (string) $row->peran,
            'bobot' => (string) self::desimal($row->getRawOriginal('bobot')), 'urutan' => (int) $row->urutan];
    }
}
