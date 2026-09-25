<?php

namespace App\Services\Kinerja;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use Illuminate\Support\Collection;

/**
 * Service domain perhitungan indikator kinerja berbasis komponen (Data-Driven).
 *
 * Mengelola validasi struktur komponen, kontrak formula terstruktur untuk konsumsi UI,
 * dan evaluasi perhitungan formula di sisi server (Plan 2.14-2.15 & PRD §17).
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
                if ($penyebut && (float) $penyebut->bobot <= 0) {
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
        }

        return [
            'is_valid' => empty($messages),
            'messages' => $messages,
        ];
    }

    /**
     * Menghasilkan kontrak formula representatif server-side untuk dikonsumsi UI.
     * React menyajikan formula ini tanpa menjadi source of truth kalkulasi (AC-11).
     *
     * @return array{
     *     tipe_perhitungan: string,
     *     formula_text: string,
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
            'formula_text' => $formulaText,
            'is_valid' => $validation['is_valid'],
            'messages' => $validation['messages'],
            'komponen_list' => $aktifKomponen->map(fn (IndikatorKomponen $k) => [
                'id' => $k->id,
                'kode' => $k->kode,
                'label' => $k->label,
                'peran' => $k->peran,
                'bobot' => (float) $k->bobot,
                'satuan' => $k->satuan,
                'urutan' => (int) $k->urutan,
                'aktif' => (bool) $k->aktif,
            ])->values()->all(),
        ];
    }

    /**
     * Evaluasi formula kalkulasi indikator di server.
     *
     * @param  array<string, float|int|numeric-string>  $komponenValues
     */
    public function evaluate(IndikatorKinerja $indikator, array $komponenValues): ?float
    {
        /** @var Collection<int, IndikatorKomponen> $komponen */
        $komponen = $indikator->komponen ?? collect();
        $aktifKomponen = $komponen->filter(fn ($k) => (bool) $k->aktif);

        $presisi = (int) ($indikator->presisi ?? 2);

        // Seluruh komponen aktif harus memiliki nilai sebelum hasil dapat dievaluasi
        foreach ($aktifKomponen as $item) {
            if (! array_key_exists($item->kode, $komponenValues) || $komponenValues[$item->kode] === null || $komponenValues[$item->kode] === '') {
                return null;
            }
        }

        if ($indikator->tipe_perhitungan === 'rasio_persen') {
            $pembilangItems = $aktifKomponen->where('peran', 'pembilang');
            $penyebutItem = $aktifKomponen->where('peran', 'penyebut')->first();

            if (! $penyebutItem) {
                return null;
            }

            $penyebutRaw = $komponenValues[$penyebutItem->kode] ?? 0;
            $penyebutVal = (float) $penyebutRaw * (float) $penyebutItem->bobot;

            // Pembagian dengan nol menghasilkan null (tidak dapat dihitung) sesuai PRD §17.4
            if (abs($penyebutVal) < 0.000000000001) {
                return null;
            }

            $totalPembilang = 0.0;
            foreach ($pembilangItems as $item) {
                $rawVal = (float) ($komponenValues[$item->kode] ?? 0);
                $totalPembilang += $rawVal * (float) $item->bobot;
            }

            $hasil = ($totalPembilang / $penyebutVal) * 100.0;

            return round($hasil, $presisi);
        }

        if ($indikator->tipe_perhitungan === 'penjumlahan') {
            $penjumlahItems = $aktifKomponen->where('peran', 'penjumlah');
            $total = 0.0;

            foreach ($penjumlahItems as $item) {
                $rawVal = (float) ($komponenValues[$item->kode] ?? 0);
                $total += $rawVal * (float) $item->bobot;
            }

            return round($total, $presisi);
        }

        if ($indikator->tipe_perhitungan === 'manual') {
            $val = (float) ($komponenValues['nilai'] ?? 0);

            return round($val, $presisi);
        }

        return null;
    }

    /**
     * Membentuk representasi teks formula matematis.
     *
     * @param  Collection<int, IndikatorKomponen>  $aktifKomponen
     */
    private function generateFormulaText(IndikatorKinerja $indikator, Collection $aktifKomponen): string
    {
        if ($indikator->tipe_perhitungan === 'manual') {
            return 'Input Manual Langsung';
        }

        if ($aktifKomponen->isEmpty()) {
            return 'Formula belum terdefinisi (belum ada komponen aktif)';
        }

        if ($indikator->tipe_perhitungan === 'penjumlahan') {
            $penjumlah = $aktifKomponen->where('peran', 'penjumlah');
            if ($penjumlah->isEmpty()) {
                return 'Formula belum lengkap (memerlukan komponen penjumlah)';
            }

            // Pola khusus IKU 3: tepat sakip dan zi_wbk dengan bobot 0.5
            $kodes = $penjumlah->pluck('kode')->all();
            if (count($kodes) === 2 && in_array('sakip', $kodes, true) && in_array('zi_wbk', $kodes, true)) {
                $bobot1 = (float) $penjumlah->firstWhere('kode', 'sakip')->bobot;
                $bobot2 = (float) $penjumlah->firstWhere('kode', 'zi_wbk')->bobot;
                if (abs($bobot1 - 0.5) < 0.0001 && abs($bobot2 - 0.5) < 0.0001) {
                    return '(sakip × 0.5) + (zi_wbk × 0.5) = (sakip + zi_wbk) / 2';
                }
            }

            $terms = [];
            foreach ($penjumlah as $k) {
                $bobot = (float) $k->bobot;
                $terms[] = (abs($bobot - 1.0) < 0.000001) ? $k->kode : "({$k->kode} × {$bobot})";
            }

            return implode(' + ', $terms);
        }

        if ($indikator->tipe_perhitungan === 'rasio_persen') {
            $pembilang = $aktifKomponen->where('peran', 'pembilang');
            $penyebut = $aktifKomponen->where('peran', 'penyebut')->first();

            if ($pembilang->isEmpty() || ! $penyebut) {
                return 'Formula belum lengkap (memerlukan pembilang dan tepat satu penyebut)';
            }

            $pembilangTerms = [];
            foreach ($pembilang as $k) {
                $bobot = (float) $k->bobot;
                $pembilangTerms[] = (abs($bobot - 1.0) < 0.000001) ? $k->kode : "({$k->kode} × {$bobot})";
            }
            $pembilangStr = count($pembilangTerms) > 1
                ? '('.implode(' + ', $pembilangTerms).')'
                : $pembilangTerms[0];

            $penyebutBobot = (float) $penyebut->bobot;
            $penyebutStr = (abs($penyebutBobot - 1.0) < 0.000001)
                ? $penyebut->kode
                : "({$penyebut->kode} × {$penyebutBobot})";

            return "({$pembilangStr} / {$penyebutStr}) × 100%";
        }

        return 'Tipe perhitungan tidak dikenal';
    }
}
