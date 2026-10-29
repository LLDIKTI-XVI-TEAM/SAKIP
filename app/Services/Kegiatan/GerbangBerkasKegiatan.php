<?php

namespace App\Services\Kegiatan;

use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\Periode;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang tulis berkas persyaratan tahap perencanaan kegiatan (mis. RAB,
 * Q42 §42.3–§42.4) dan tahap pelaksanaan (`kegiatan`, PRD §15.6). Hak mutasi turun dari `kegiatan:update` pada unit
 * kegiatan beserta izin bacanya (Q54 §54.3); `berkas:*` hanya gerbang deny
 * (Q32.3). Penghapusan dan penggantian ditolak sejak RA pengklaim diajukan
 * (Q42 §42.4 butir 5, pola Q36) agar berkas yang dibekukan versi RA tetap
 * berlaku; koreksi lewat pengembalian RA lalu berkas pengganti. Kunci itu hanya
 * untuk tahap perencanaan; berkas tahap pelaksanaan dikunci status `terlaksana`.
 * Izin dijawab 403, pelanggaran bisnis 422. `kunci()` wajib di dalam transaksi.
 */
class GerbangBerkasKegiatan
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{induk: PermissionDecision, tolak: ?PermissionDecision} */
    public function periksaIzin(User $user, Kegiatan $kegiatan, string $izinBerkas): array
    {
        $unitId = (string) $kegiatan->unit_id;
        $induk = $this->resolver->resolve($user, PermissionCodes::KEGIATAN_UPDATE, $unitId);
        if (! $induk->allowed) {
            return ['induk' => $induk, 'tolak' => $induk];
        }
        $baca = $this->resolver->resolve($user, PermissionCodes::KEGIATAN_READ, $unitId);
        if (! $baca->allowed) {
            return ['induk' => $induk, 'tolak' => $baca];
        }
        $berkas = $this->resolver->resolve($user, $izinBerkas, $unitId);

        return ['induk' => $induk, 'tolak' => in_array($berkas->basis['alasan'] ?? null, GerbangBuktiRencanaAksi::ALASAN_TERTUTUP, true) ? $berkas : null];
    }

    /** Deny `berkas:read` pada unit kegiatan menutup baca/unduh berkas meski detail kegiatan terbuka (Q32.3, pola `viewEvidence`). */
    public function bacaTertutup(User $user, Kegiatan $kegiatan): bool
    {
        $baca = $this->resolver->resolve($user, PermissionCodes::BERKAS_READ, (string) $kegiatan->unit_id);

        return in_array($baca->basis['alasan'] ?? null, GerbangBuktiRencanaAksi::ALASAN_TERTUTUP, true);
    }

    /**
     * @param  Collection<int, RencanaAksi>  $pengklaim  Header RA dengan klaim tahap RA atas kegiatan ini.
     * @return array{0: string, 1: string}|null
     */
    public function pelanggaranBisnis(Kegiatan $kegiatan, Collection $pengklaim, bool $cabut, bool $tahunDitutup, string $tahap = PersyaratanPerencanaanKegiatan::TAHAP): ?array
    {
        if (Unit::whereKey((string) $kegiatan->unit_id)->sharedLock()->value('status') !== 'aktif') {
            return ['unit_id', 'Unit pemilik kegiatan berstatus nonaktif.'];
        }
        if ($tahunDitutup) {
            return ['tahun', 'Tahun kegiatan sudah ditutup; perubahan hanya melalui pembukaan kembali jadwal.'];
        }
        // `$cabut`: hapus atau ganti, yaitu aksi yang mengakhiri keberlakuan berkas lama.
        if ($cabut && $kegiatan->status === 'terlaksana') {
            return ['berkas', 'Berkas kegiatan yang sudah terlaksana tidak dapat dihapus atau diganti.'];
        }
        if ($cabut && $tahap === PersyaratanPerencanaanKegiatan::TAHAP && $pengklaim->contains(fn (RencanaAksi $header): bool => ! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true))) {
            return ['berkas', 'Berkas perencanaan kegiatan tidak dapat dihapus atau diganti sejak rencana aksi yang mengklaimnya diajukan; koreksi lewat pengembalian rencana aksi.'];
        }

        return null;
    }

    public function catatTolakTepi(User $user, Kegiatan $kegiatan, PermissionDecision $keputusan, string $tindakan): void
    {
        $this->audit->catat(actor: $user, tindakan: $tindakan, objekTipe: 'kegiatan', objekId: (string) $kegiatan->id,
            alasan: 'Percobaan mutasi berkas perencanaan kegiatan ditolak oleh sistem otorisasi.', dasarIzin: $keputusan->toAuditBasis());
    }

    /**
     * Urutan kunci searah pengajuan RA dan `SimpanKegiatan`: aktor, konfigurasi
     * periode, header RA pengklaim, jadwal, kegiatan, lalu berkas oleh pemanggil.
     *
     * @param  array<string, mixed>|null  $dasarIzin
     * @param  string  $tahap  Tahap persyaratan berkas yang disentuh; menentukan kunci hapus/ganti.
     * @return array{pengunci: User, kegiatan: Kegiatan, pengklaim: Collection<int, RencanaAksi>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function kunci(User $actor, string $kegiatanId, string $izinBerkas, bool $cabut, ?array &$dasarIzin, string $tahap = PersyaratanPerencanaanKegiatan::TAHAP): array
    {
        $kunci = $this->lockedActor->handle($actor, PermissionCodes::KEGIATAN_UPDATE);
        $pengunci = $kunci['aktor'];
        if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
            $dasarIzin = $kunci['keputusan']->toAuditBasis();
            throw new AuthorizationException('Akun pengguna tidak aktif.');
        }
        Periode::lockConfiguration();
        $headerIds = self::headerPengklaim($kegiatanId);
        $pengklaim = RencanaAksi::whereIn('id', $headerIds)->orderBy('id')->sharedLock()->get();
        // Tahun dibaca sebelum kegiatan dikunci hanya sebagai kandidat, lalu dicek ulang (pola SimpanKegiatan).
        $tahun = Kegiatan::whereKey($kegiatanId)->value('tahun');
        $ditutup = self::tahunDitutup((int) $tahun);
        $kegiatan = Kegiatan::lockForUpdate()->findOrFail($kegiatanId);

        $izin = $this->periksaIzin($pengunci, $kegiatan, $izinBerkas);
        $dasarIzin = ($izin['tolak'] ?? $izin['induk'])->toAuditBasis();
        if ($izin['tolak'] !== null) {
            throw new AuthorizationException('Izin mutasi berkas kegiatan tidak tersedia atau telah dicabut.');
        }
        // Klaim baru wajib mengunci kegiatan; selisih hanya mungkin sebelum kegiatan terkunci.
        if (self::headerPengklaim($kegiatanId) !== $headerIds || (int) $kegiatan->tahun !== (int) $tahun) {
            throw ValidationException::withMessages(['berkas' => 'Klaim kegiatan telah berubah. Muat ulang sebelum mengubah berkas.']);
        }
        $pelanggaran = $this->pelanggaranBisnis($kegiatan, $pengklaim, $cabut, $ditutup, $tahap);
        if ($pelanggaran !== null) {
            throw ValidationException::withMessages([$pelanggaran[0] => $pelanggaran[1]]);
        }

        return ['pengunci' => $pengunci, 'kegiatan' => $kegiatan, 'pengklaim' => $pengklaim];
    }

    /** Kegiatan tidak terikat Renstra: tahun terkunci bila jadwal tahun itu mana pun sudah ditutup. */
    public static function tahunDitutup(int $tahun): bool
    {
        return JadwalTahunan::where('tahun', $tahun)->where('status', '!=', 'draft')->orderBy('id')->sharedLock()->get()
            ->contains(fn (JadwalTahunan $jadwal): bool => $jadwal->tahunDitutup());
    }

    /** @return list<string> */
    public static function headerPengklaim(string $kegiatanId): array
    {
        return KlaimKegiatan::where('kegiatan_id', $kegiatanId)->where('sumber_klaim', 'rencana_aksi')->distinct()
            ->orderBy('rencana_aksi_id')->pluck('rencana_aksi_id')->map(fn ($id): string => (string) $id)->all();
    }
}
