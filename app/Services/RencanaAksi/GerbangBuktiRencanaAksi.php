<?php

namespace App\Services\RencanaAksi;

use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\RencanaAksi;
use App\Models\Unit;
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
 * hapus, capability halaman, dan Policy. Izin (`periksaIzin`) dan validasi
 * bisnis (`pelanggaranBisnis`) sengaja dipisah sesuai Data Model §3.2
 * langkah 6: kegagalan bisnis dijawab 422, bukan "izin ditolak".
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
     * Izin mutasi bukti: allow berasal dari `rencana_aksi:update` pada unit
     * header; `berkas:*` bukan permission unit-scoped (Q32.3) sehingga hanya
     * diperiksa sebagai gerbang deny/fail-closed.
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
     * Validasi bisnis tulis bukti yang sejajar dengan `SimpanTargetPeriode`:
     * unit header aktif, indikator bukan arsip, status header dapat
     * disunting, dan jendela tulis (penutupan, PIC efektif, rentang tanggal).
     *
     * @return array{0: string, 1: string}|null Pasangan [field, pesan] pelanggaran pertama.
     */
    public function pelanggaranBisnis(User $aktor, PermissionDecision $induk, RencanaAksi $header, IndikatorKinerja $indikator, JadwalTahunan $jadwal): ?array
    {
        if (Unit::whereKey((string) $header->unit_id)->sharedLock()->value('status') !== 'aktif') {
            return ['unit_id', 'Unit pemilik rencana aksi berstatus nonaktif.'];
        }
        if ($indikator->isArsip()) {
            return ['indikator_id', "Indikator '{$indikator->kode}' telah diarsipkan sehingga bukti rencana aksinya tidak dapat diubah."];
        }
        if (! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
            return ['status_alur', 'Bukti hanya dapat diubah pada rencana aksi yang masih dapat disunting.'];
        }
        $alasan = $this->jendela->alasanTolak($aktor, $induk, $indikator, $jadwal, 'penyimpanan');

        return $alasan === null ? null : ['jendela', $alasan];
    }

    /**
     * Capability halaman tanpa lock dan tanpa audit, memakai aturan yang sama
     * dengan `kunci()` agar UI tidak menawarkan aksi yang pasti ditolak.
     */
    public function bolehMutasi(User $user, RencanaAksi $header, string $izinBerkas): bool
    {
        $izin = $this->periksaIzin($user, $header, $izinBerkas);
        if ($izin['tolak'] !== null) {
            return false;
        }
        $header->loadMissing(['indikator', 'jadwalTahunan']);

        return $this->pelanggaranBisnis($user, $izin['induk'], $header, $header->indikator, $header->jadwalTahunan) === null;
    }

    /**
     * Audit penolakan izin untuk request mutasi langsung (dipanggil
     * FormRequest). Tidak dipakai capability halaman agar kunjungan biasa
     * tidak tercatat sebagai percobaan unggah/hapus.
     */
    public function catatTolakTepi(User $user, RencanaAksi $header, string $izinBerkas, string $tindakan): void
    {
        $izin = $this->periksaIzin($user, $header, $izinBerkas);

        $this->audit->catat(
            actor: $user,
            tindakan: $tindakan,
            objekTipe: 'rencana_aksi',
            objekId: (string) $header->id,
            alasan: 'Percobaan mutasi bukti dukung rencana aksi ditolak oleh sistem otorisasi.',
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

        $pelanggaran = $this->pelanggaranBisnis($pengunci, $izin['induk'], $header, $indikator, $jadwal);
        if ($pelanggaran !== null) {
            throw ValidationException::withMessages([$pelanggaran[0] => $pelanggaran[1]]);
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
