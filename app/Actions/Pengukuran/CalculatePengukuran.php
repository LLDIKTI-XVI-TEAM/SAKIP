<?php

namespace App\Actions\Pengukuran;

use Illuminate\Validation\ValidationException;

class CalculatePengukuran
{
    /** Nilai dihitung dari definisi snapshot; nol faktual berbeda dari input hilang. */
    public function handle(string $type, int $precision, array $definitions, array $values, ?float $manual): array
    {
        $source = $type === 'manual' ? 'manual' : 'komponen';
        $result = function (?float $value, string $status) use ($source): array {
            if ($value !== null && (! is_finite($value) || abs($value) >= 1e18)) {
                throw new \InvalidArgumentException('Hasil perhitungan melebihi kapasitas penyimpanan angka.');
            }

            return ['nilai' => $value, 'status_perhitungan' => $status, 'sumber_nilai' => $source];
        };
        if ($type === 'manual') {
            return $manual === null ? $result(null, 'belum_diisi') : $result(round($manual, $precision), 'terhitung');
        }
        if (! in_array($type, ['rasio_persen', 'penjumlahan'], true)) {
            throw ValidationException::withMessages(['nilai' => 'Tipe perhitungan snapshot tidak sah.']);
        }
        $ids = array_column($definitions, 'komponen_id');
        if (array_diff(array_keys($values), $ids)) {
            throw ValidationException::withMessages(['komponen' => 'Komponen bukan anggota snapshot ini.']);
        }
        $roles = array_count_values(array_column($definitions, 'peran'));
        if (($type === 'rasio_persen' && (($roles['pembilang'] ?? 0) < 1 || ($roles['penyebut'] ?? 0) !== 1 || ($roles['penjumlah'] ?? 0) > 0))
            || ($type === 'penjumlahan' && (($roles['penjumlah'] ?? 0) < 1 || count($definitions) !== ($roles['penjumlah'] ?? 0)))) {
            throw ValidationException::withMessages(['komponen' => 'Definisi komponen snapshot tidak memenuhi tipe perhitungan.']);
        }
        $complete = true;
        foreach ($ids as $id) {
            if (! array_key_exists($id, $values) || $values[$id] === null) {
                $complete = false;

                continue;
            }
            if (! is_numeric($values[$id]) || ! is_finite((float) $values[$id]) || abs((float) $values[$id]) >= 1e18) {
                throw new \InvalidArgumentException('Nilai komponen tidak sah atau melebihi kapasitas penyimpanan.');
            }
        }
        if (! $complete) {
            return $result(null, 'belum_diisi');
        }
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($definitions as $definition) {
            $weighted = (float) $values[$definition['komponen_id']] * (float) $definition['bobot'];
            if ($definition['peran'] === 'penyebut') {
                $denominator = $weighted;
            } else {
                $numerator += $weighted;
            }
        }
        if ($type === 'rasio_persen' && $denominator == 0.0) {
            return $result(null, 'tidak_dapat_dihitung');
        }

        return $result(round($type === 'rasio_persen' ? $numerator / $denominator * 100 : $numerator, $precision), 'terhitung');
    }
}
