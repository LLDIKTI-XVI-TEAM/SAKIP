<?php

namespace App\Actions\RencanaAksi;

use App\Events\RencanaAksiDibukaKembali;
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

class BukaKembaliRencanaAksi
{
    public function __construct(private readonly GerbangReviewRencanaAksi $gerbang, private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        $jejak = [];
        try {
            return DB::transaction(function () use ($actor, $id, $data, &$jejak) {
                [$pengunci, $header, $versi] = $this->gerbang->kunci($actor, $id, $data, PermissionCodes::RENCANA_AKSI_BUKA_KEMBALI, $jejak);
                // Q43 §43.2: alasan buka-kembali WAJIB, sama seperti kembalikan.
                Validator::make($data, ['alasan' => ['required', 'string', 'max:1000', AuditReason::validate(...)]])->validate();
                $alasan = trim(AuditReason::sanitize($data['alasan']));
                if ($alasan === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan buka kembali wajib diisi.']);
                }
                $this->gerbang->pastikan($pengunci, $header, $versi, 'buka_kembali');
                // Isi snapshot dilonggarkan seperti kembalikan agar versi yang
                // isinya rusak dapat diganti versi baru, bukan terjebak aturan
                // validasi baru. Buka-kembali tidak memperpanjang deadline PIC;
                // jendela tulis tetap ditegakkan saat simpan target. Versi
                // pengajuan yang disahkan tidak diubah; hanya header kembali
                // ke jalur revisi. Perencanaan yang mengesahkan boleh membuka
                // kembali; kontrol tersisa permission sensitif, alasan wajib,
                // dan audit dasar izin.
                $penerima = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
                    ->where('indikator_id', $header->indikator_id)->value('user_id');
                $header->status_alur = RencanaAksi::STATUS_DIKEMBALIKAN;
                $header->versi++;
                $header->save();
                $nilaiBaru = ['status_alur' => $header->status_alur, 'versi' => $header->versi, 'penerima_id' => $penerima]
                    + $this->gerbang->provenance($pengunci, $versi, persetujuan: false);
                $this->audit->catat($pengunci, 'rencana_aksi.buka_kembali', 'rencana_aksi', $id, $jejak['sebelum'],
                    $nilaiBaru,
                    $alasan, $jejak['dasar_izin']);
                RencanaAksiDibukaKembali::dispatch($id, $versi->id, $versi->diajukan_by, $penerima, $pengunci->id, $alasan);

                return $header;
            });
        } catch (AuthorizationException|ValidationException $e) {
            $this->gerbang->catatPenolakan($actor, $id, 'buka_kembali', $jejak, $e);
            throw $e;
        }
    }
}
