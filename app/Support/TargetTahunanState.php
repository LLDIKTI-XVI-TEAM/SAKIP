<?php

namespace App\Support;

use App\Models\IndikatorKinerja;
use App\Models\Renstra;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pembaca/penulis berbagi urutan lock dan state; transaksi serta izin tetap milik Action. */
class TargetTahunanState
{
    /** @return array{indikator: IndikatorKinerja, sasaran: SasaranStrategis, renstra: Renstra, target: ?TargetKinerja, tahun: int, has_snapshot: bool} */
    public static function lock(string $indikatorId, int $tahun, bool $writing = false): array
    {
        $indikator = IndikatorKinerja::whereKey($indikatorId)->lock($writing ? true : 'for share')->firstOrFail();
        $sasaran = SasaranStrategis::whereKey($indikator->sasaran_strategis_id)->sharedLock()->firstOrFail();
        $renstra = Renstra::whereKey($sasaran->renstra_id)->sharedLock()->firstOrFail();
        if ($tahun < $renstra->tahun_mulai || $tahun > $renstra->tahun_selesai || $tahun < $indikator->tahun_mulai_berlaku) {
            throw ValidationException::withMessages(['tahun' => 'Tahun harus berada dalam rentang Renstra dan tidak sebelum indikator mulai berlaku.']);
        }
        $target = TargetKinerja::where('indikator_kinerja_id', $indikator->id)->where('tahun', $tahun)->lock($writing ? true : 'for share')->first();
        // Seluruh versi/status dihitung. Writer snapshot kelak wajib mengunci konteks yang sama sebelum menyalin nilai.
        $hasSnapshot = DB::table('jadwal_snapshot as snapshot')->join('jadwal_tahunan as jadwal', 'jadwal.id', '=', 'snapshot.jadwal_id')
            ->where('snapshot.indikator_id', $indikator->id)->where('jadwal.tahun', $tahun)->where('jadwal.renstra_id', $renstra->id)->exists();

        return ['indikator' => $indikator, 'sasaran' => $sasaran, 'renstra' => $renstra, 'target' => $target, 'tahun' => $tahun, 'has_snapshot' => $hasSnapshot];
    }

    /** Nilai canonical menjaga null/0 serta tidak memperpendek baseline mengikuti presisi target. @return array{baseline: ?string, target_tahunan: ?string} */
    public static function values(?TargetKinerja $target): array
    {
        return ['baseline' => TargetTahunanDecimal::normalize($target?->baseline, 'baseline'), 'target_tahunan' => TargetTahunanDecimal::normalize($target?->target_tahunan)];
    }

    /** HMAC allowlist persisted state, bukan izin atau payload request. @param array<string, mixed> $state */
    public static function token(array $state): string
    {
        $payload = ['indikator' => $state['indikator']->only(['id', 'sasaran_strategis_id', 'status', 'presisi', 'tahun_mulai_berlaku', 'updated_at']),
            'sasaran' => $state['sasaran']->only(['id', 'renstra_id', 'updated_at']), 'renstra' => $state['renstra']->masterAttributes(),
            'tahun' => $state['tahun'], 'target' => $state['target']?->only(['id', 'baseline', 'target_tahunan', 'updated_by', 'updated_at']), 'has_snapshot' => $state['has_snapshot']];

        return hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), Crypt::getKey());
    }

    /** @param array<string, mixed> $state */
    public static function readOnlyReason(array $state): ?string
    {
        return match (true) {
            $state['indikator']->status !== IndikatorKinerja::STATUS_AKTIF => 'Indikator arsip hanya dapat dibaca.',
            ! in_array($state['renstra']->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true) => 'Renstra nonaktif atau diarsipkan hanya dapat dibaca.',
            default => null,
        };
    }
}
