<?php

namespace App\Actions\Pengukuran;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CalculatePengukuran
{
    /** Nilai dihitung dari definisi snapshot; nol faktual berbeda dari input hilang. */
    public function handle(string $type, int $precision, array $definitions, array $values, string|int|float|null $manual): array
    {
        if ($precision < 0 || $precision > 12) {
            throw new InvalidArgumentException('Presisi snapshot melebihi kapasitas penyimpanan angka.');
        }
        $source = $type === 'manual' ? 'manual' : 'komponen';
        $result = function (?BigDecimal $value, string $status) use ($source): array {
            if ($value !== null && $value->abs()->isGreaterThanOrEqualTo('1000000000000000000')) {
                throw new InvalidArgumentException('Hasil perhitungan melebihi kapasitas penyimpanan angka.');
            }

            return ['nilai' => $value === null ? null : (string) $value, 'status_perhitungan' => $status, 'sumber_nilai' => $source];
        };
        if ($type === 'manual') {
            return $manual === null ? $result(null, 'belum_diisi') : $result($this->decimal($manual)->toScale($precision, RoundingMode::HalfUp), 'terhitung');
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
            $values[$id] = $this->decimal($values[$id], true);
        }
        if (! $complete) {
            return $result(null, 'belum_diisi');
        }
        $numerator = BigDecimal::of('0');
        $denominator = BigDecimal::of('0');
        foreach ($definitions as $definition) {
            $weighted = $values[$definition['komponen_id']]->multipliedBy($this->decimal($definition['bobot']));
            if ($definition['peran'] === 'penyebut') {
                $denominator = $weighted;
            } else {
                $numerator = $numerator->plus($weighted);
            }
        }
        if ($type === 'rasio_persen' && $denominator->isZero()) {
            return $result(null, 'tidak_dapat_dihitung');
        }

        // Bulatkan hanya hasil akhir; komponen dan pembobotan tetap desimal eksak.
        $value = $type === 'rasio_persen'
            ? $numerator->multipliedBy('100')->dividedBy($denominator, $precision, RoundingMode::HalfUp)
            : $numerator->toScale($precision, RoundingMode::HalfUp);

        return $result($value, 'terhitung');
    }

    private function decimal(mixed $value, bool $storedComponent = false): BigDecimal
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Nilai harus berupa angka desimal.');
        }
        try {
            $decimal = BigDecimal::of((string) $value);
            if ($storedComponent) {
                // Komponen mentah harus tersimpan utuh agar snapshot dan perhitungan memakai input yang sama.
                $decimal = $decimal->toScale(12);
            }
        } catch (MathException $exception) {
            throw new InvalidArgumentException('Nilai tidak sah atau memiliki lebih dari 12 digit desimal.', previous: $exception);
        }
        if ($decimal->abs()->isGreaterThanOrEqualTo('1000000000000000000')) {
            throw new InvalidArgumentException('Nilai melebihi kapasitas penyimpanan angka.');
        }

        return $decimal;
    }
}
