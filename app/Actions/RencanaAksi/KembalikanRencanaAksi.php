<?php

namespace App\Actions\RencanaAksi;

use App\Events\RencanaAksiDikembalikan;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RencanaAksi\GerbangReviewRencanaAksi;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KembalikanRencanaAksi
{
    public function __construct(private readonly GerbangReviewRencanaAksi $gerbang, private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        $jejak = [];
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$jejak) {
                [$pengunci, $header, $versi] = $this->gerbang->kunci($actor, $id, $data, PermissionCodes::RENCANA_AKSI_KEMBALIKAN, $jejak);
                Validator::make($data, ['alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)]])->validate();
                $alasan = trim(AuditReason::sanitize($data['alasan']));
                if ($alasan === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan pengembalian wajib diisi.']);
                }
                $this->gerbang->pastikan($pengunci, $header, $versi, 'kembalikan');
                // Writer PJ juga mengunci indikator; pemenang efektif tidak dapat berganti selama transaksi ini.
                $penerima = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
                    ->where('indikator_id', $header->indikator_id)->value('user_id');
                $header->status_alur = RencanaAksi::STATUS_DIKEMBALIKAN;
                $header->versi++;
                $header->save();
                $this->audit->catat($pengunci, 'rencana_aksi.kembalikan', 'rencana_aksi', $id, $jejak['sebelum'],
                    ['status_alur' => $header->status_alur, 'versi' => $header->versi, 'penerima_id' => $penerima] + $this->gerbang->provenance($pengunci, $versi, persetujuan: false),
                    $alasan, $jejak['dasar_izin']);
                RencanaAksiDikembalikan::dispatch($id, $versi->id, $versi->diajukan_by, $penerima, $pengunci->id, $alasan);

                return $header;
            });
        } catch (AuthorizationException|ValidationException $e) {
            $this->gerbang->catatPenolakan($actor, $id, 'kembalikan', $jejak, $e);
            throw $e;
        }
    }
}
