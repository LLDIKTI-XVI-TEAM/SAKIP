<?php

namespace App\Services\RencanaAksi;

use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang tulis bukti rencana aksi yang dipakai bersama aksi tambah dan
 * hapus. Mengunci aktor, header, indikator, dan jadwal dengan urutan yang
 * sama seperti `SimpanTargetPeriode`, lalu memeriksa ulang izin induk,
 * deny `berkas:*`, status header, dan jendela PIC pada state terkunci.
 * `kunci()` wajib dipanggil di dalam transaksi pemanggil.
 */
class GerbangBuktiRencanaAksi
{
    /** Alasan resolver yang menutup akses walau izin induk mengizinkan (deny menang, fail-closed). */
    public const ALASAN_TERTUTUP = ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'];

    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Satu sumber aturan izin mutasi bukti untuk Policy (capability/fail-fast)
     * dan `kunci()` (state terkunci). Allow berasal dari `rencana_aksi:update`
     * pada unit header; `berkas:*` bukan permission unit-scoped (Q32.3)
     * sehingga hanya diperiksa sebagai gerbang deny/fail-closed.
     *
     * @return array{induk: PermissionDecision, tolak: ?PermissionDecision}
     */
    public function periksaIzin(User $user, RencanaAksi $header, string $izinBerkas): array
    {
        $unitId = (string) $header->unit_id;
        $induk = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId);
        if (! $induk->allowed) {
            return ['induk' => $induk, 'tolak' => $induk];
        }

        $berkas = $this->resolver->resolve($user, $izinBerkas, $unitId);

        return ['induk' => $induk, 'tolak' => in_array($berkas->basis['alasan'] ?? null, self::ALASAN_TERTUTUP, true) ? $berkas : null];
    }

    /**
     * Audit penolakan request mutasi langsung (dipanggil FormRequest). Tidak
     * dipakai saat menghitung capability halaman agar kunjungan biasa tidak
     * tercatat sebagai percobaan unggah/hapus.
     */
    public function catatTolakTepi(User $user, RencanaAksi $header, string $izinBerkas, string $tindakan): void
    {
        $izin = $this->periksaIzin($user, $header, $izinBerkas);

        $this->audit->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'rencana_aksi',
            objekId: (string) $header->id,
            alasan: $izin['tolak'] !== null
                ? 'Percobaan mutasi bukti dukung rencana aksi ditolak oleh sistem otorisasi.'
                : 'Bukti hanya dapat diubah pada rencana aksi yang masih dapat disunting.',
            dasarIzin: ($izin['tolak'] ?? $izin['induk'])->toAuditBasis(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $dasarIzin  Diisi dengan dasar keputusan terakhir untuk audit pemanggil.
     * @return array{pengunci: User, header: RencanaAksi}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function kunci(User $actor, string $id, string $izinBerkas, ?array &$dasarIzin): array
    {
        $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_UPDATE);
        $pengunci = $kunci['aktor'];
        if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
            $dasarIzin = $kunci['keputusan']->toAuditBasis();
            throw new AuthorizationException('Akun pengguna tidak aktif.');
        }

        $header = RencanaAksi::lockForUpdate()->findOrFail($id);
        $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id);
        $jadwal = JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id);

        $izin = $this->periksaIzin($pengunci, $header, $izinBerkas);
        $dasarIzin = ($izin['tolak'] ?? $izin['induk'])->toAuditBasis();
        if ($izin['tolak'] !== null) {
            throw new AuthorizationException('Izin mutasi bukti rencana aksi tidak tersedia atau telah dicabut.');
        }

        if (! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
            throw ValidationException::withMessages(['status_alur' => 'Bukti hanya dapat diubah pada rencana aksi yang masih dapat disunting.']);
        }

        $alasan = $this->jendela->alasanTolak($pengunci, $izin['induk'], $indikator, $jadwal, 'penyimpanan');
        if ($alasan !== null) {
            throw ValidationException::withMessages(['jendela' => $alasan]);
        }

        return ['pengunci' => $pengunci, 'header' => $header];
    }

    /**
     * Metadata bukti untuk audit (Plan 13.9): file tanpa `path`, teks hanya panjangnya.
     *
     * @return array<string, mixed>
     */
    public static function metadataAudit(BuktiDukung $bukti): array
    {
        return match ($bukti->mode) {
            'file' => ['mode' => 'file', 'jenis_berkas_id' => $bukti->jenis_berkas_id, 'nama_asli' => $bukti->nama_asli, 'mime' => $bukti->mime, 'ukuran_bytes' => $bukti->ukuran_bytes],
            'tautan' => ['mode' => 'tautan', 'jenis_berkas_id' => $bukti->jenis_berkas_id, 'tautan' => $bukti->tautan],
            default => ['mode' => 'teks', 'jenis_berkas_id' => $bukti->jenis_berkas_id, 'panjang_teks' => mb_strlen((string) $bukti->isi_teks)],
        };
    }
}
