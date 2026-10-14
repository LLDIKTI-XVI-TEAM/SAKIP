<?php

namespace App\Services\Kegiatan;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang tulis daftar kegiatan tahap Rencana Aksi (klaim `sumber_klaim =
 * rencana_aksi`). Menambah atau menghapus klaim tahap RA adalah perubahan RA
 * (Q41 §41.2), sehingga izin kegiatan pada unit kegiatan harus disertai
 * kewenangan mutasi RA pada unit header, dan keduanya tunduk pada status
 * header serta gerbang tulis RA (penutupan, PIC efektif, kunci periode).
 * Izin dijawab 403, pelanggaran bisnis 422 (Data Model §3.2 langkah 6).
 * `kunci()` wajib dipanggil di dalam transaksi pemanggil.
 */
class GerbangKlaimKegiatan
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly JendelaTulisRencanaAksi $jendela,
    ) {}

    /**
     * Allow wajib dari `rencana_aksi:update` pada unit header dan izin
     * kegiatan pada unit kegiatan; penolakan salah satunya menutup akses.
     * Keputusan RA menentukan jalur Perencanaan pada gerbang tulis.
     *
     * @return array{ra: PermissionDecision, kegiatan: ?PermissionDecision, tolak: ?PermissionDecision}
     */
    public function periksaIzin(User $user, string $unitHeader, string $unitKegiatan, string $izinKegiatan): array
    {
        $ra = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, $unitHeader);
        if (! $ra->allowed) {
            return ['ra' => $ra, 'kegiatan' => null, 'tolak' => $ra];
        }
        $kegiatan = $this->resolver->resolve($user, $izinKegiatan, $unitKegiatan);

        return ['ra' => $ra, 'kegiatan' => $kegiatan, 'tolak' => $kegiatan->allowed ? null : $kegiatan];
    }

    /**
     * Dasar izin audit: keputusan yang menolak, atau kedua keputusan yang mengizinkan.
     *
     * @param  array{ra: PermissionDecision, kegiatan: ?PermissionDecision, tolak: ?PermissionDecision}  $izin
     * @return array<string, mixed>
     */
    public static function dasarIzin(array $izin): array
    {
        if ($izin['tolak'] !== null) {
            return $izin['tolak']->toAuditBasis();
        }

        return ['rencana_aksi' => $izin['ra']->toAuditBasis(), 'kegiatan' => $izin['kegiatan']?->toAuditBasis()];
    }

    /**
     * Validasi bisnis yang sejajar dengan gerbang tulis bukti RA, ditambah
     * dimensi periode kegiatan untuk kunci per periode bagi PIC (Q41 §41.2).
     *
     * @param  list<string>  $periodeIds
     * @return array{0: string, 1: string}|null Pasangan [field, pesan] pelanggaran pertama.
     */
    public function pelanggaranBisnis(User $aktor, PermissionDecision $ra, RencanaAksi $header, IndikatorKinerja $indikator, JadwalTahunan $jadwal, array $periodeIds): ?array
    {
        if (Unit::whereKey((string) $header->unit_id)->sharedLock()->value('status') !== 'aktif') {
            return ['unit_id', 'Unit pemilik rencana aksi berstatus nonaktif.'];
        }
        if ($indikator->isArsip()) {
            return ['indikator_id', "Indikator '{$indikator->kode}' telah diarsipkan sehingga daftar kegiatan rencana aksinya tidak dapat diubah."];
        }
        // Klaim tahap RA ikut dibekukan saat pengajuan (Q24, Q26); setelah diajukan hanya berubah lewat pengembalian.
        if (! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
            return ['status_alur', 'Daftar kegiatan hanya dapat diubah pada rencana aksi berstatus draf atau dikembalikan.'];
        }
        $alasan = $this->jendela->alasanTolak($aktor, $ra, $indikator, $jadwal, 'penyimpanan', $periodeIds);

        return $alasan === null ? null : ['jendela', $alasan];
    }

    /**
     * Mengunci aktor/ACL, konfigurasi periode, header, indikator, jadwal, lalu kegiatan (bila ada)
     * dengan urutan tetap header → kegiatan agar serial dengan pengajuan
     * Pengukuran yang mengunci header sebelum klaim dan kegiatan.
     *
     * @param  list<string>  $periodeIds  Periode yang hendak diubah daftar kegiatannya.
     * @param  array<string, mixed>|null  $dasarIzin  Diisi dasar keputusan terakhir untuk audit pemanggil.
     * @return array{pengunci: User, header: RencanaAksi, indikator: IndikatorKinerja, jadwal: JadwalTahunan, kegiatan: ?Kegiatan, ra: PermissionDecision}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function kunci(User $actor, string $headerId, ?string $kegiatanId, string $izinKegiatan, array $periodeIds, ?array &$dasarIzin): array
    {
        $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_UPDATE);
        $pengunci = $kunci['aktor'];
        if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
            $dasarIzin = $kunci['keputusan']->toAuditBasis();
            throw new AuthorizationException('Akun pengguna tidak aktif.');
        }

        // Selaras writer kegiatan dan jadwal: aktor/ACL, lalu konfigurasi periode, baru objek domain.
        Periode::lockConfiguration();
        $header = RencanaAksi::lockForUpdate()->find($headerId)
            ?? throw ValidationException::withMessages(['rencana_aksi_id' => 'Rencana aksi tidak ditemukan.']);
        $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id);
        $jadwal = JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id);
        $kegiatan = $kegiatanId === null ? null : Kegiatan::lockForUpdate()->findOrFail($kegiatanId);
        if ($kegiatan !== null) {
            $periodeIds = [...$periodeIds, (string) $kegiatan->periode_id];
        }

        $izin = $this->periksaIzin($pengunci, (string) $header->unit_id, (string) ($kegiatan?->unit_id ?? $header->unit_id), $izinKegiatan);
        $dasarIzin = self::dasarIzin($izin);
        if ($izin['tolak'] !== null) {
            throw new AuthorizationException('Izin mengubah daftar kegiatan rencana aksi tidak tersedia atau telah dicabut.');
        }

        $pelanggaran = $this->pelanggaranBisnis($pengunci, $izin['ra'], $header, $indikator, $jadwal, array_values(array_unique($periodeIds)));
        if ($pelanggaran !== null) {
            throw ValidationException::withMessages([$pelanggaran[0] => $pelanggaran[1]]);
        }

        return ['pengunci' => $pengunci, 'header' => $header, 'indikator' => $indikator, 'jadwal' => $jadwal, 'kegiatan' => $kegiatan, 'ra' => $izin['ra']];
    }
}
