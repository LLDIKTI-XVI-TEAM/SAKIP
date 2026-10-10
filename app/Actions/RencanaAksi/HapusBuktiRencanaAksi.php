<?php

namespace App\Actions\RencanaAksi;

use App\Models\BuktiDukung;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RencanaAksi\GerbangBuktiRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Hapus bukti rencana aksi sebelum batas imutabilitas (PRD §18.8): soft
 * delete beralasan, file fisik dipertahankan karena versi pengajuan dapat
 * merujuknya.
 */
class HapusBuktiRencanaAksi
{
    public function __construct(
        private readonly GerbangBuktiRencanaAksi $gerbang,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(User $actor, string $id, string $buktiId, string $alasan): void
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            DB::transaction(function () use ($actor, $id, $buktiId, $alasan, &$dasarIzin): void {
                ['pengunci' => $pengunci, 'header' => $header] = $this->gerbang->kunci($actor, $id, PermissionCodes::BERKAS_DELETE, $dasarIzin);

                // Bukti induk lain dijawab 404 agar keberadaannya tidak bocor.
                /** @var BuktiDukung $bukti */
                $bukti = $header->buktiDukungs()->current()->whereKey($buktiId)->lockForUpdate()->firstOrFail();
                $bukti->update(['dihapus_pada' => now(), 'dihapus_oleh' => $pengunci->id]);

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'berkas.hapus',
                    objekTipe: 'berkas',
                    objekId: (string) $bukti->id,
                    nilaiLama: [...GerbangBuktiRencanaAksi::metadataAudit($bukti), 'rencana_aksi_id' => (string) $header->id],
                    nilaiBaru: ['dihapus_pada' => $bukti->dihapus_pada?->toIso8601String()],
                    alasan: AlasanAudit::sanitasi($alasan, 'Penghapusan bukti dukung rencana aksi.'),
                    dasarIzin: $dasarIzin,
                );
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)
                && RencanaAksi::whereKey($id)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'berkas.hapus_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: $id,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Penghapusan bukti dukung rencana aksi ditolak.'),
                    dasarIzin: $dasarIzin,
                );
            }

            throw $exception;
        }
    }
}
