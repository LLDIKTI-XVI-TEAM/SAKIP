<?php

namespace App\Actions\TargetRenstra;

use App\Models\IndikatorKinerja;
use App\Models\RevisiTargetRenstra;
use App\Models\RevisiTargetRenstraNilai;
use App\Models\SasaranStrategis;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\TargetRenstra\GerbangRevisiTargetRenstra;
use App\Services\TargetRenstra\PenulisTargetRenstra;
use App\Support\AuditReason;
use App\Support\TargetRenstraState;
use App\Support\TargetTahunanDecimal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Menyelesaikan revisi (Q71 §71.2–§71.3): semua sel yang berubah diterapkan dalam satu transaksi dengan satu justifikasi,
 * nilai lama masuk histori bernomor, dan setiap sel diaudit lewat jalur tulis yang sama dengan simpan langsung.
 */
class SimpanRevisiTargetRenstra
{
    public const FIELDS = ['versi', 'justifikasi'];

    public function __construct(private readonly GerbangRevisiTargetRenstra $gerbang, private readonly PenulisTargetRenstra $penulis) {}

    /** @param  array<string, mixed>  $data */
    public function handle(User $actor, string $renstraId, array $data): RevisiTargetRenstra
    {
        return $this->gerbang->jalankan($actor, $renstraId, 'simpan', function (array $basis) use ($actor, $renstraId, $data): RevisiTargetRenstra {
            if (array_diff(array_keys($data), self::FIELDS) !== []) {
                throw ValidationException::withMessages(['nilai' => 'Permintaan memuat field yang tidak didukung.']);
            }
            $data = Validator::make($data, [
                'versi' => ['required', 'integer', 'min:1'],
                'justifikasi' => ['required', 'string', 'max:1000', AuditReason::validate(...)],
            ], [
                'versi.*' => 'Data form tidak valid. Muat ulang form.',
                'justifikasi.required' => 'Justifikasi revisi wajib diisi.',
                'justifikasi.string' => 'Justifikasi revisi wajib diisi.',
                'justifikasi.max' => 'Justifikasi maksimal 1.000 karakter.',
            ])->validate();

            // Urutan lock sama dengan simpan langsung dan target PK: indikator → sasaran → Renstra → baris target, lalu draf.
            $sasaran = SasaranStrategis::select('id')->where('renstra_id', $renstraId);
            $indikator = IndikatorKinerja::whereIn('sasaran_strategis_id', $sasaran)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            SasaranStrategis::where('renstra_id', $renstraId)->orderBy('id')->sharedLock()->get();
            $renstra = $this->gerbang->renstraAktif($renstraId);
            $target = TargetRenstra::whereIn('indikator_id', $indikator->keys())->orderBy('indikator_id')->orderBy('tahun')->lockForUpdate()->get()->groupBy('indikator_id');
            $draf = $this->gerbang->drafTerkunci($renstra, (int) $data['versi']);

            $perubahan = [];
            foreach ($draf->nilai()->orderBy('indikator_id')->orderBy('tahun')->get() as $sel) {
                $baris = $this->gerbang->periksaIndikator($indikator->get($sel->indikator_id), $sel->indikator_id);
                $rows = $target->get($baris->id, collect())->keyBy('tahun');
                $berlaku = TargetRenstraState::values($rows);
                $baru = $this->gerbang->periksaSel($baris, $renstra, $sel->tahun, TargetTahunanDecimal::normalize($sel->nilai_baru), $berlaku);
                if ($baru !== ($berlaku[$sel->tahun] ?? null)) {
                    $perubahan[] = ['indikator' => $baris, 'tahun' => $sel->tahun, 'baris' => $rows->get($sel->tahun), 'lama' => $berlaku[$sel->tahun] ?? null, 'baru' => $baru];
                }
            }
            if ($perubahan === []) {
                throw ValidationException::withMessages(['nilai' => 'Tidak ada nilai yang berubah, revisi tidak dapat disimpan.']);
            }

            // Hanya satu draf per Renstra dan draf itu terkunci, sehingga nomor berikutnya tidak dapat diambil bersamaan.
            $nomor = (int) RevisiTargetRenstra::where('renstra_id', $renstra->id)->where('status', RevisiTargetRenstra::STATUS_DISIMPAN)->max('nomor') + 1;
            foreach ($perubahan as $p) {
                $this->penulis->tulis($actor, $p['indikator']->id, $p['tahun'], $p['baris'], $p['lama'], $p['baru'], $basis, $data['justifikasi'], ['revisi_nomor' => $nomor]);
            }
            // Histori hanya memuat sel yang benar-benar berubah, lengkap dengan nilai sebelum revisi.
            $draf->nilai()->delete();
            RevisiTargetRenstraNilai::insert(array_map(fn (array $p): array => ['id' => (string) Str::uuid(), 'revisi_id' => $draf->id,
                'indikator_id' => $p['indikator']->id, 'tahun' => $p['tahun'], 'nilai_lama' => $p['lama'], 'nilai_baru' => $p['baru']], $perubahan));
            $draf->fill(['status' => RevisiTargetRenstra::STATUS_DISIMPAN, 'nomor' => $nomor, 'justifikasi' => $data['justifikasi'], 'versi' => $draf->versi + 1,
                'disimpan_oleh' => $actor->id, 'disimpan_pada' => now()])->save();

            return $draf;
        });
    }
}
