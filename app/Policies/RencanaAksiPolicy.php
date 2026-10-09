<?php

namespace App\Policies;

use App\Models\IndikatorKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;

class RencanaAksiPolicy
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly AuditLogger $audit,
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
     * Penolakan tepi dicatat agar selaras audit transaksi Action.
     */
    public function create(User $user, IndikatorKinerja $indikator): Response
    {
        $decision = $this->resolver->resolve($user, PermissionCodes::RENCANA_AKSI_CREATE, (string) $indikator->unit_id);

        if (! $decision->allowed) {
            $this->catatBuatDitolak($user, $decision);
        }

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

        if (! $decision->allowed) {
            $this->catatUbahDitolak($user, $header, $decision);
        }

        return $this->response($decision, 'Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.');
    }

    private function response(PermissionDecision $decision, string $pesan): Response
    {
        return $decision->allowed ? Response::allow() : Response::deny($pesan);
    }

    private function catatBuatDitolak(User $user, PermissionDecision $decision): void
    {
        $this->audit->catat(
            actor: $user,
            tindakan: 'rencana_aksi.buat_ditolak',
            objekTipe: 'rencana_aksi',
            objekId: (string) Str::uuid(),
            alasan: AlasanAudit::sanitasi(null, 'Percobaan pembuatan rencana aksi ditolak oleh sistem otorisasi.'),
            dasarIzin: $decision->toAuditBasis(),
        );
    }

    private function catatUbahDitolak(User $user, RencanaAksi $header, PermissionDecision $decision): void
    {
        $this->audit->catat(
            actor: $user,
            tindakan: 'rencana_aksi.ubah_ditolak',
            objekTipe: 'rencana_aksi',
            objekId: (string) $header->id,
            nilaiLama: $header->withoutRelations()->toArray(),
            alasan: AlasanAudit::sanitasi(null, 'Percobaan penyimpanan target rencana aksi ditolak oleh sistem otorisasi.'),
            dasarIzin: $decision->toAuditBasis(),
        );
    }
}
