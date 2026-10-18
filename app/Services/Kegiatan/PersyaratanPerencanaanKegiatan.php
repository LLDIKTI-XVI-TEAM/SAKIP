<?php

namespace App\Services\Kegiatan;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\BuktiDukung;
use App\Models\JenisBerkas;
use App\Models\Kegiatan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Persyaratan tahap perencanaan kegiatan per kegiatan (Q42 §42.3–§42.4):
 * persyaratan global ditambah persyaratan khusus indikator yang diminta,
 * dinilai dengan evaluator bukti bersama. Persyaratan bertanda
 * `hanya_kegiatan_beranggaran` hanya berlaku bila anggaran lebih dari 0;
 * anggaran kosong tetap mewajibkannya karena belum terbukti nol.
 */
class PersyaratanPerencanaanKegiatan
{
    public const TAHAP = 'kegiatan_perencanaan';

    private const TANPA_INDIKATOR = '00000000-0000-0000-0000-000000000000';

    public function __construct(private readonly EvaluateEvidence $evidence) {}

    /**
     * @param  list<string>  $indikatorIds  Indikator RA pengklaim; kosong berarti hanya persyaratan global.
     * @param  Collection<int, BuktiDukung>|null  $bukti  Bukti berlaku kegiatan; dimuat bila tidak diberikan.
     * @return list<array<string, mixed>>
     */
    public function evaluasi(Kegiatan $kegiatan, array $indikatorIds, ?Collection $bukti = null): array
    {
        $bukti ??= self::buktiBerlaku((string) $kegiatan->id)->get();
        // UUID nol tidak cocok indikator mana pun sehingga evaluator hanya memuat persyaratan global.
        $hasil = collect($indikatorIds === [] ? [self::TANPA_INDIKATOR] : array_values(array_unique($indikatorIds)))
            ->flatMap(fn (string $indikatorId): array => $this->evidence->untuk(self::TAHAP, $indikatorId, $bukti))
            ->unique('id')->values();
        $khususBeranggaran = JenisBerkas::whereIn('id', $hasil->pluck('id'))->where('hanya_kegiatan_beranggaran', true)->pluck('id')->all();
        // Cast decimal:0 menormalkan nol menjadi "0".
        $tanpaAnggaran = $kegiatan->anggaran === '0';

        return $hasil->reject(fn (array $item): bool => $tanpaAnggaran && in_array($item['id'], $khususBeranggaran, true))
            ->map(fn (array $item): array => $item + ['hanya_kegiatan_beranggaran' => in_array($item['id'], $khususBeranggaran, true)])
            ->values()->all();
    }

    /**
     * Nama persyaratan wajib yang belum terpenuhi.
     *
     * @param  list<array<string, mixed>>  $evaluasi
     * @return list<string>
     */
    public static function kurang(array $evaluasi): array
    {
        return array_values(array_map(fn (array $item): string => (string) $item['nama'],
            array_filter($evaluasi, fn (array $item): bool => $item['wajib'] && ! $item['pemenuhan']['terpenuhi'])));
    }

    /** @return Builder<BuktiDukung> */
    public static function buktiBerlaku(string $kegiatanId): Builder
    {
        return BuktiDukung::where('berkasable_type', 'kegiatan')->where('berkasable_id', $kegiatanId)->current();
    }
}
