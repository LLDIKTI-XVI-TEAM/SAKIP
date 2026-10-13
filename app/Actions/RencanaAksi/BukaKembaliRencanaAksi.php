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
                // Q7 Opsi B (divergensi dokumen, addendum pending): alasan OPSIONAL khusus buka_kembali.
                // PRD §14.6 / Plan 11.7 / ISS-05.06 masih menulis wajib, tetapi stakeholder memerintahkan
                // opsional. Validasi nullable; sanitasi hanya bila terisi; kosong/null lolos dan audit memakai fallback.
                Validator::make($data, ['alasan' => ['nullable', 'string', 'max:1000', AuditReason::validate(...)]])->validate();
                $mentah = $data['alasan'] ?? null;
                $alasan = is_string($mentah) ? trim(AuditReason::sanitize($mentah)) : '';
                $this->gerbang->pastikan($pengunci, $header, $versi, 'buka_kembali');
                // Q5: isi-snapshot dilonggarkan seperti kembalikan (lihat GerbangReviewRencanaAksi) agar versi
                // yang isinya rusak dapat diganti versi baru, bukan terjebak aturan validasi baru.
                // Q6: buka-kembali tidak memperpanjang deadline PIC; jendela tulis tetap ditegakkan saat simpan target.
                // Versi pengajuan yang disahkan tidak diubah; hanya header kembali ke jalur revisi.
                $penerima = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())
                    ->where('indikator_id', $header->indikator_id)->value('user_id');
                $header->status_alur = RencanaAksi::STATUS_DIKEMBALIKAN;
                $header->versi++;
                $header->save();
                // Q2: penanda darurat bila Superadmin membuka pengesahannya sendiri; non-superadmin sama-orang
                // sudah ditolak di GerbangReviewRencanaAksi::businessErrors dan tercatat via catatPenolakan.
                $darurat = $versi->disahkan_by !== null && $versi->disahkan_by === $pengunci->id && $pengunci->hasRole('superadmin');
                $alasanAudit = $alasan !== '' ? $alasan : null;
                $alasanEvent = $alasan !== '' ? $alasan : 'Pencatatan audit untuk tindakan rencana_aksi.buka_kembali.';
                $nilaiBaru = ['status_alur' => $header->status_alur, 'versi' => $header->versi, 'penerima_id' => $penerima]
                    + $this->gerbang->provenance($pengunci, $versi, persetujuan: false)
                    + ($darurat ? ['pembuka_sama_dengan_pengesah' => true] : []);
                $this->audit->catat($pengunci, 'rencana_aksi.buka_kembali', 'rencana_aksi', $id, $jejak['sebelum'],
                    $nilaiBaru,
                    $alasanAudit, $jejak['dasar_izin']);
                RencanaAksiDibukaKembali::dispatch($id, $versi->id, $versi->diajukan_by, $penerima, $pengunci->id, $alasanEvent);

                return $header;
            });
        } catch (AuthorizationException|ValidationException $e) {
            $this->gerbang->catatPenolakan($actor, $id, 'buka_kembali', $jejak, $e);
            throw $e;
        }
    }
}
