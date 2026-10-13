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

class VerifikasiRencanaAksi
{
    public function __construct(private readonly GerbangReviewRencanaAksi $gerbang, private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        $jejak = [];
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$jejak) {
                [$pengunci, $header, $versi] = $this->gerbang->kunci($actor, $id, $data, PermissionCodes::RENCANA_AKSI_VERIFIKASI, $jejak);
                $this->gerbang->pastikan($pengunci, $header, $versi, 'verifikasi');
                $header->status_alur = RencanaAksi::STATUS_DIVERIFIKASI;
                $header->versi++;
                $header->save();
                $this->audit->catat($pengunci, 'rencana_aksi.verifikasi', 'rencana_aksi', $id, $jejak['sebelum'],
                    ['status_alur' => $header->status_alur, 'versi' => $header->versi] + $this->gerbang->provenance($pengunci, $versi),
                    'Memverifikasi versi pengajuan rencana aksi.', $jejak['dasar_izin']);

                return $header;
            });
        } catch (AuthorizationException|ValidationException $e) {
            $this->gerbang->catatPenolakan($actor, $id, 'verifikasi', $jejak, $e);
            throw $e;
        }
    }
}
