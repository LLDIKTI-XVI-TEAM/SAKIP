<?php

namespace App\Services\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Validasi komposisi dan representasi formula eksak untuk create, simpan,
 * pembacaan editor serta preview. Tidak memiliki otorisasi, transaksi, locking,
 * persistence atau audit; seluruh workflow tetap milik Action pemanggil.
 * Kalkulasi angka hanya dilakukan CalculatePengukuran, bukan Service ini.
 */
class IndikatorPerhitunganService
{
    /**
     * Validasi aturan definisi komponen terhadap tipe perhitungan indikator.
     *
     * @return array{is_valid: bool, messages: list<string>}
     */
    public function validateDefinisiKomponen(IndikatorKinerja $indikator): array
    {
        /** @var Collection<int, IndikatorKomponen> $komponen */
        $komponen = $indikator->komponen ?? collect();
        $aktifKomponen = $komponen->filter(fn ($k) => (bool) $k->aktif);

        $messages = [];

        if ($indikator->tipe_perhitungan === 'rasio_persen') {
            $pembilangCount = $aktifKomponen->where('peran', 'pembilang')->count();
            $penyebutCount = $aktifKomponen->where('peran', 'penyebut')->count();
            $penjumlahCount = $aktifKomponen->where('peran', 'penjumlah')->count();

            if ($pembilangCount < 1) {
                $messages[] = 'Indikator bertipe rasio persen wajib memiliki minimal satu pembilang aktif.';
            }

            if ($penyebutCount === 0) {
                $messages[] = 'Indikator bertipe rasio persen wajib memiliki tepat satu penyebut aktif.';
            } elseif ($penyebutCount > 1) {
                $messages[] = 'Indikator bertipe rasio persen tidak boleh memiliki lebih dari satu penyebut aktif.';
            } else {
                $penyebut = $aktifKomponen->firstWhere('peran', 'penyebut');
                if ($penyebut && BigDecimal::of((string) $penyebut->bobot)->isLessThanOrEqualTo('0')) {
                    $messages[] = 'Komponen penyebut pada indikator bertipe rasio persen wajib memiliki bobot lebih besar dari 0 agar formula dapat dihitung.';
                }
            }

            if ($penjumlahCount > 0) {
                $messages[] = 'Indikator bertipe rasio persen tidak boleh memiliki komponen berperan penjumlah.';
            }
        } elseif ($indikator->tipe_perhitungan === 'penjumlahan') {
            $penjumlahCount = $aktifKomponen->where('peran', 'penjumlah')->count();

            if ($penjumlahCount < 1) {
                $messages[] = 'Indikator bertipe penjumlahan wajib memiliki minimal satu komponen penjumlah aktif.';
            }

            if ($aktifKomponen->count() !== $penjumlahCount) {
                $messages[] = 'Seluruh komponen aktif pada indikator bertipe penjumlahan wajib berperan sebagai penjumlah.';
            }
        } elseif ($indikator->tipe_perhitungan === 'manual') {
            if ($aktifKomponen->count() > 0) {
                $messages[] = 'Indikator bertipe manual tidak menggunakan komponen angka perhitungan.';
            }
        }

        return [
            'is_valid' => empty($messages),
            'messages' => $messages,
        ];
    }

    /**
     * Menghasilkan kontrak formula representatif server-side untuk dikonsumsi UI.
     * React menyajikan formula ini tanpa menjadi sumber kebenaran kalkulasi.
     *
     * @return array{
     *     tipe_perhitungan: string,
     *     formula_text: string,
     *     formula_unavailable: ?string,
     *     is_valid: bool,
     *     messages: list<string>,
     *     komponen_list: list<array<string, mixed>>
     * }
     */
    public function getFormulaContract(IndikatorKinerja $indikator): array
    {
        $validation = $this->validateDefinisiKomponen($indikator);
        /** @var Collection<int, IndikatorKomponen> $komponen */
        $komponen = $indikator->komponen ?? collect();
        $aktifKomponen = $komponen->filter(fn ($k) => (bool) $k->aktif)->sortBy('urutan');

        $formulaText = $this->generateFormulaText($indikator, $aktifKomponen);

        return [
            'tipe_perhitungan' => (string) $indikator->tipe_perhitungan,
            'formula_text' => $formulaText ?? '',
            'formula_unavailable' => $formulaText === null ? 'Formula terlalu panjang untuk ditampilkan. Definisi tetap dapat dibaca dan diperbaiki.' : null,
            'is_valid' => $validation['is_valid'],
            'messages' => $validation['messages'],
            'komponen_list' => $aktifKomponen->map(fn (IndikatorKomponen $k) => [
                'id' => $k->id,
                'kode' => $k->kode,
                'label' => $k->label,
                'peran' => $k->peran,
                'bobot' => (string) $k->bobot,
                'satuan' => $k->satuan,
                'urutan' => (int) $k->urutan,
                'aktif' => (bool) $k->aktif,
            ])->values()->all(),
        ];
    }

    /** Representasi literal dibatasi 64 KiB; formula besar tidak dipotong seolah lengkap. */
    private function generateFormulaText(IndikatorKinerja $indikator, Collection $aktifKomponen): ?string
    {
        if ($indikator->tipe_perhitungan === 'manual') {
            return 'Input Manual Langsung';
        }
        if ($aktifKomponen->isEmpty()) {
            return 'Formula belum terdefinisi (belum ada komponen aktif)';
        }
        $terms = [];
        $denominator = null;
        $bytes = 0;
        foreach ($aktifKomponen as $row) {
            $weight = BigDecimal::of((string) $row->bobot)->strippedOfTrailingZeros();
            $term = $weight->isEqualTo('1') ? $row->kode : "({$row->kode} × {$weight})";
            $bytes += strlen($term) + 5;
            if ($bytes > 65500) {
                return null;
            }
            if ($row->peran === 'penyebut') {
                $denominator = $term;
            } else {
                $terms[] = $term;
            }
        }
        if ($indikator->tipe_perhitungan === 'penjumlahan') {
            return implode(' + ', $terms);
        }
        if ($terms === [] || $denominator === null) {
            return 'Formula belum lengkap (memerlukan pembilang dan tepat satu penyebut)';
        }
        $numerator = implode(' + ', $terms);

        return "(({$numerator}) / {$denominator}) × 100%";
    }
}
