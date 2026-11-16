<?php

namespace App\Policies;

use App\Models\IndikatorKinerja;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiVersi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Services\RencanaAksi\GerbangReviewRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Log;

/**
 * Policy murni tanpa efek samping: penolakan dicatat pemanggil yang tahu
 * konteksnya (FormRequest simpan/buat, atau Action di dalam transaksi),
 * sehingga Gate yang sama dapat dipakai pratinjau tanpa tercatat sebagai
 * percobaan simpan.
 */
class RencanaAksiPolicy
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly GerbangReviewRencanaAksi $review,
    ) {}

    /**
     * Baca daftar memakai izin global; scope unit ditegakkan pada tiap baris via view().
     */
    public function viewAny(User $user): bool
    {
        return $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_READ);
    }

    /**
     * Baca satu header memakai izin baca global yang diuji terhadap unit header.
     *
     * Fail-closed saat unit snapshot versi pengajuan terbaru tidak selaras dengan
     * unit header (data beku tidak boleh dibaca memakai scope unit berbeda);
     * anomali dicatat. Deny unit-spesifik maupun global tetap menang via resolver;
     * tanpa audit baca agar penolakan awal tidak membanjiri jejak audit.
     */
    public function view(User $user, RencanaAksi $header): bool
    {
        return $this->dapatDilihat($header, $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_READ, (string) $header->unit_id));
    }

    /**
     * Capability daftar lintas unit: keputusan izin unit sudah dihitung pemanggil
     * sekali untuk semua unit yang tampil (`PermissionResolver::decideManyForUnits`)
     * agar ACL tidak dibaca ulang per baris, sedangkan keselarasan unit snapshot
     * versi terbaru tetap ditegakkan di sini supaya aturannya tidak terduplikasi
     * di Action.
     */
    public function dapatDilihat(RencanaAksi $header, bool $unitDiizinkan): bool
    {
        $terbaru = $header->relationLoaded('latestVersion') ? $header->getRelation('latestVersion') : $header->latestVersion()->first();

        return $this->unitSnapshotSelaras($header, $terbaru instanceof RencanaAksiVersi ? $terbaru : null) && $unitDiizinkan;
    }

    /** Versi yang dipilih juga dicek agar data beku unit lain tidak terbaca lewat versi historis (`?versi=`, unduhan bukti). */
    public function viewVersi(User $user, RencanaAksi $header, RencanaAksiVersi $versi): bool
    {
        return $this->unitSnapshotSelaras($header, $versi) && $this->view($user, $header);
    }

    /**
     * Buat draf memakai unit indikator calon header (belum ada baris).
     *
     * PIC memakai grant unit + penugasan efektif (diperiksa Action/jendela);
     * Perencanaan lolos via peran global tanpa grant unit. Deny menang.
     */
    public function create(User $user, IndikatorKinerja $indikator): Response
    {
        $decision = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_CREATE, (string) $indikator->unit_id);

        return $this->response($decision, 'Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.');
    }

    /**
     * Simpan target memakai unit snapshot header (bukan unit indikator berjalan).
     *
     * Hak tulis mengikuti unit header + PIC efektif saat transaksi (Action);
     * penanggung jawab historis header bukan dasar izin berjalan.
     */
    public function update(User $user, RencanaAksi $header): Response
    {
        $decision = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $header->unit_id);

        return $this->response($decision, 'Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.');
    }

    /**
     * Lihat/unduh bukti mengikuti akses induk (pola PengukuranKinerjaPolicy):
     * allow `berkas:read` atau hak tulis unit; deny/katalog nonaktif menang.
     */
    public function viewEvidence(User $user, RencanaAksi $header): bool
    {
        if (! $this->view($user, $header)) {
            return false;
        }
        $unitId = (string) $header->unit_id;
        $decision = $this->resolver->decide($user, PermissionCodes::BERKAS_READ, $unitId);
        if (in_array($decision['reason'], GerbangBuktiRencanaAksi::ALASAN_TERTUTUP, true)) {
            return false;
        }

        return $decision['allowed']
            || $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId)
            || $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_CREATE, $unitId);
    }

    /** Tanpa versi atau tanpa unit snapshot dianggap selaras; anomali dicatat. */
    private function unitSnapshotSelaras(RencanaAksi $header, ?RencanaAksiVersi $versi): bool
    {
        $snapshotUnit = $versi?->jadwalSnapshot?->unit_id;
        if ($snapshotUnit === null || (string) $snapshotUnit === (string) $header->unit_id) {
            return true;
        }
        Log::warning('rencana_aksi.unit_snapshot_tidak_konsisten', [
            'rencana_aksi_id' => $header->id, 'rencana_aksi_versi_id' => $versi->id, 'unit_id' => $header->unit_id, 'snapshot_unit_id' => (string) $snapshotUnit,
        ]);

        return false;
    }

    private function response(PermissionDecision $decision, string $pesan): Response
    {
        return $decision->allowed ? Response::allow() : Response::deny($pesan);
    }

    public function verifikasi(User $user, RencanaAksi $header): Response
    {
        return $this->response($this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_VERIFIKASI, (string) $header->unit_id), 'Izin verifikasi rencana aksi tidak tersedia.');
    }

    public function kembalikan(User $user, RencanaAksi $header): Response
    {
        return $this->response($this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_KEMBALIKAN, (string) $header->unit_id), 'Izin pengembalian rencana aksi tidak tersedia.');
    }

    public function sahkan(User $user, RencanaAksi $header): Response
    {
        return $this->response($this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_SAHKAN, (string) $header->unit_id), 'Izin pengesahan rencana aksi tidak tersedia.');
    }

    public function bukaKembali(User $user, RencanaAksi $header): Response
    {
        return $this->response($this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_BUKA_KEMBALI, (string) $header->unit_id), 'Izin buka-kembali rencana aksi tidak tersedia.');
    }

    /** @return array<string, list<string>> */
    public function businessErrors(User $user, RencanaAksi $header, ?RencanaAksiVersi $versi): array
    {
        return ['verifikasi' => $this->review->businessErrors($user, $header, $versi, 'verifikasi'),
            'kembalikan' => $this->review->businessErrors($user, $header, $versi, 'kembalikan'),
            'sahkan' => $this->review->businessErrors($user, $header, $versi, 'sahkan'),
            'buka_kembali' => $this->review->businessErrors($user, $header, $versi, 'buka_kembali')];
    }
}
