<?php

namespace App\Services\Audit;

use App\Models\AuditLog;

/**
 * Menyusun tampilan baca audit dengan allowlist kolom.
 *
 * Payload `nilai_lama`/`nilai_baru`/`dasar_izin` ditulis banyak modul sejak fase awal, sehingga
 * kunci yang menyerupai rahasia disamarkan di server sebelum menjadi props: seluruh props dapat
 * dibaca klien meskipun tidak dirender. Baris audit sendiri tidak diubah (append-only).
 */
final class AuditLogPresenter
{
    public const DISAMARKAN = '[disamarkan]';

    /** Kunci token/password/secret/authorization/credential, api/access/private key, serta `path` atau `*_path` (jalur storage privat). */
    private const KUNCI_RAHASIA = '/token|password|secret|authorization|credential|(api|access|private)[_-]?key|(^|_)path$/i';

    /**
     * Ringkasan baris daftar tanpa payload perubahan.
     *
     * @return array{id: string, waktu: string, tindakan: string, objek_tipe: string, objek_id: string, actor_type: string, sumber: string, aktor: array{nama: string, email: string}|null, alasan: string|null, ditolak: bool}
     */
    public static function ringkasan(AuditLog $audit): array
    {
        return [
            'id' => $audit->id,
            'waktu' => $audit->waktu->toIso8601String(),
            'tindakan' => $audit->tindakan,
            'objek_tipe' => $audit->objek_tipe,
            'objek_id' => $audit->objek_id,
            'actor_type' => $audit->actor_type,
            'sumber' => $audit->sumber,
            'aktor' => $audit->actor?->only(['nama', 'email']),
            'alasan' => $audit->alasan,
            // Seluruh modul menamai percobaan yang ditolak dengan akhiran `_ditolak` atau `.ditolak`.
            'ditolak' => str_ends_with($audit->tindakan, 'ditolak'),
        ];
    }

    /** @return array<string, mixed> */
    public static function detail(AuditLog $audit): array
    {
        return [
            ...self::ringkasan($audit),
            'operator_reference' => $audit->operator_reference,
            'runtime_identity' => $audit->runtime_identity,
            'nilai_lama' => self::samarkan($audit->nilai_lama),
            'nilai_baru' => self::samarkan($audit->nilai_baru),
            'dasar_izin' => self::samarkan($audit->dasar_izin),
        ];
    }

    /** Mengganti nilai di bawah kunci rahasia secara rekursif; kunci lain dan urutannya dipertahankan. */
    public static function samarkan(mixed $nilai): mixed
    {
        if (! is_array($nilai)) {
            return $nilai;
        }
        foreach ($nilai as $kunci => $isi) {
            $nilai[$kunci] = is_string($kunci) && preg_match(self::KUNCI_RAHASIA, $kunci) === 1 ? self::DISAMARKAN : self::samarkan($isi);
        }

        return $nilai;
    }
}
