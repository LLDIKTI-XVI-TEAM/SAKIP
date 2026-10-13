<?php

namespace App\Actions\RencanaAksi;

use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RencanaAksi\GerbangReviewRencanaAksi;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SahkanRencanaAksi
{
    public function __construct(private readonly GerbangReviewRencanaAksi $gerbang, private readonly AuditLogger $audit) {}

    /**
     * Pengesahan diverifikasi → disahkan memakai gerbang review bersama (D1):
     * izin, F1/F2, versi pengajuan terbaru, status, konteks, dan audit pada
     * mutation boundary yang sama dengan verifikasi/pengembalian.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        $jejak = [];
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$jejak) {
                [$pengunci, $header, $versi] = $this->gerbang->kunci($actor, $id, $data, PermissionCodes::RENCANA_AKSI_SAHKAN, $jejak);
                $this->gerbang->pastikan($pengunci, $header, $versi, 'sahkan');
                $waktu = now();
                // Metadata pengesahan versi dan header ditulis atomik; trigger DB hanya mengizinkannya sekali.
                $versi->update(['disahkan_by' => $pengunci->id, 'disahkan_at' => $waktu]);
                $header->status_alur = RencanaAksi::STATUS_DISAHKAN;
                $header->disahkan_by = $pengunci->id;
                $header->disahkan_at = $waktu;
                $header->versi++;
                $header->save();
                $this->audit->catat($pengunci, 'rencana_aksi.sahkan', 'rencana_aksi', $id, $jejak['sebelum'],
                    ['status_alur' => $header->status_alur, 'versi' => $header->versi, 'disahkan_at' => $waktu->toIso8601String()] + $this->gerbang->provenance($pengunci, $versi),
                    'Mengesahkan versi pengajuan rencana aksi.', $jejak['dasar_izin']);

                return $header;
            });
        } catch (AuthorizationException|ValidationException $e) {
            $this->gerbang->catatPenolakan($actor, $id, 'sahkan', $jejak, $e);
            throw $e;
        }
    }
}
