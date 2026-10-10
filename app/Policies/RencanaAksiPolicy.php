<?php

namespace App\Policies;

use App\Models\IndikatorKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;

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
     * Deny unit-spesifik maupun global tetap menang via resolver; tanpa audit
     * baca agar penolakan awal tidak membanjiri jejak audit.
     */
    public function view(User $user, RencanaAksi $header): bool
    {
        return $this->resolver->allows($user, PermissionCodes::RENCANA_AKSI_READ, (string) $header->unit_id);
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

    private function response(PermissionDecision $decision, string $pesan): Response
    {
        return $decision->allowed ? Response::allow() : Response::deny($pesan);
    }
}
