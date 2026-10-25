<?php

namespace App\Actions\TargetRenstra;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\RevisiTargetRenstra;
use App\Models\RevisiTargetRenstraNilai;
use App\Models\User;
use App\Services\TargetRenstra\GerbangRevisiTargetRenstra;
use App\Support\TargetRenstraState;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Autosave draf revisi: hanya sel yang dikirim yang disimpan, dan target berlaku tidak tersentuh sampai revisi disimpan.
 * Tidak diaudit karena belum mengubah target; penolakan tetap diaudit lewat gerbang.
 */
class SimpanDrafRevisiTargetRenstra
{
    /** Field kontrak; field lain ditolak agar tidak diterapkan diam-diam. */
    public const FIELDS = ['versi', 'nilai', 'justifikasi'];

    public function __construct(private readonly GerbangRevisiTargetRenstra $gerbang) {}

    /** @param  array<string, mixed>  $data */
    public function handle(User $actor, string $renstraId, array $data): RevisiTargetRenstra
    {
        return $this->gerbang->jalankan($actor, $renstraId, 'draf', function () use ($renstraId, $data): RevisiTargetRenstra {
            if (array_diff(array_keys($data), self::FIELDS) !== []) {
                throw ValidationException::withMessages(['nilai' => 'Permintaan memuat field yang tidak didukung.']);
            }
            // Number JSON ditolak agar nilai tidak pernah melewati float.
            $data = Validator::make($data, [
                'versi' => ['required', 'integer', 'min:1'],
                'nilai' => ['present', 'array'],
                'nilai.*' => ['array'],
                'nilai.*.*' => ['nullable', 'string', 'max:40'],
                'justifikasi' => ['sometimes', 'nullable', 'string', 'max:1000'],
            ], [
                'versi.*' => 'Data form tidak valid. Muat ulang form.',
                'nilai.present' => 'Data target belum lengkap. Muat ulang form.',
                'nilai.array' => 'Data target belum lengkap. Muat ulang form.',
                'nilai.*.array' => 'Data target belum lengkap. Muat ulang form.',
                'nilai.*.*.string' => 'Nilai target harus berupa teks angka desimal.',
                'nilai.*.*.max' => 'Angka target terlalu panjang.',
                'justifikasi.max' => 'Justifikasi maksimal 1.000 karakter.',
            ])->validate();
            $renstra = $this->gerbang->renstraAktif($renstraId);
            $draf = $this->gerbang->drafTerkunci($renstra, (int) $data['versi']);

            $baris = array_map(fn (array $sel): array => ['id' => (string) Str::uuid(), 'revisi_id' => $draf->id, ...$sel], $this->sel($renstra, $data['nilai']));
            RevisiTargetRenstraNilai::upsert($baris, ['revisi_id', 'indikator_id', 'tahun'], ['nilai_baru']);
            $draf->fill(['versi' => $draf->versi + 1, ...(array_key_exists('justifikasi', $data) ? ['justifikasi' => $data['justifikasi']] : [])])->save();

            return $draf;
        });
    }

    /**
     * @param  array<array-key, mixed>  $nilai
     * @return list<array{indikator_id: string, tahun: int, nilai_baru: ?string}>
     */
    private function sel(Renstra $renstra, array $nilai): array
    {
        foreach (array_keys($nilai) as $id) {
            if (! is_string($id) || ! Str::isUuid($id)) {
                throw ValidationException::withMessages(['nilai' => 'Indikator tidak dikenal. Muat ulang form.']);
            }
        }
        $indikator = IndikatorKinerja::with('targetRenstra')->whereIn('id', array_map('strtolower', array_keys($nilai)))
            ->whereHas('sasaranStrategis', fn ($query) => $query->where('renstra_id', $renstra->id))->get()->keyBy('id');

        $sel = [];
        foreach ($nilai as $id => $tahunan) {
            $baris = $this->gerbang->periksaIndikator($indikator->get(strtolower($id)), $id);
            $berlaku = TargetRenstraState::values($baris->targetRenstra);
            foreach ($tahunan as $tahun => $raw) {
                $nilaiBaru = $this->gerbang->periksaSel($baris, $renstra, $tahun, $raw, $berlaku);
                $sel[] = ['indikator_id' => $baris->id, 'tahun' => (int) $tahun, 'nilai_baru' => $nilaiBaru];
            }
        }

        return $sel;
    }
}
