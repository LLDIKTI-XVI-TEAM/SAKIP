<?php

namespace App\Actions\TargetRenstra;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TargetRenstra\GerbangRevisiTargetRenstra;
use App\Support\TargetTahunanDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Membuang draf revisi tanpa mengubah target (Q71 §71.2 butir 4). Draf dipakai bersama, sehingga pembuangannya diaudit
 * beserta isiannya agar pekerjaan yang dibuang tetap dapat ditelusuri.
 */
class BatalRevisiTargetRenstra
{
    public const FIELDS = ['versi'];

    public function __construct(private readonly GerbangRevisiTargetRenstra $gerbang, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function handle(User $actor, string $renstraId, array $data): void
    {
        $this->gerbang->jalankan($actor, $renstraId, 'batal', function (array $basis) use ($actor, $renstraId, $data): void {
            if (array_diff(array_keys($data), self::FIELDS) !== []) {
                throw ValidationException::withMessages(['nilai' => 'Permintaan memuat field yang tidak didukung.']);
            }
            $data = Validator::make($data, ['versi' => ['required', 'integer', 'min:1']], ['versi.*' => 'Data form tidak valid. Muat ulang form.'])->validate();
            $renstra = $this->gerbang->renstraAktif($renstraId);
            $draf = $this->gerbang->drafTerkunci($renstra, (int) $data['versi']);

            $sel = $draf->nilai()->orderBy('indikator_id')->orderBy('tahun')->get()->map(fn ($s): array => [
                'indikator_id' => $s->indikator_id, 'tahun' => $s->tahun, 'nilai' => TargetTahunanDecimal::normalize($s->nilai_baru),
            ])->all();
            $this->audit->catat(actor: $actor, tindakan: 'target_renstra.revisi_batal', objekTipe: 'revisi_target_renstra', objekId: $draf->id,
                nilaiLama: ['renstra_id' => $renstra->id, 'versi' => $draf->versi, 'justifikasi' => $draf->justifikasi, 'sel' => $sel], dasarIzin: $basis);
            $draf->delete();
        });
    }
}
