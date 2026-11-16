<?php

namespace App\Services\Jadwal;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Memusatkan validasi aturan kalender yang digunakan bersama oleh Action
 * SaveJadwalDraft, SavePeriode, dan RevisiJendelaPic, serta oleh
 * JadwalActivationReadiness untuk blocker kalender_tidak_valid, agar
 * aturan keempatnya konsisten.
 *
 * Action tetap menangani use case secara utuh: otorisasi, pengambilan
 * data, locking, transaksi, penyimpanan, dan audit. Untuk
 * JadwalActivationReadiness, transaksi dan lock aktivasi milik ActivateJadwal,
 * sedangkan ReadJadwalActivation hanya membaca dalam satu snapshot read-only.
 * Service ini hanya memvalidasi data yang diberikan pemanggil dan melempar
 * ValidationException jika aturan dilanggar.
 */
class JadwalDraftRules
{
    public const WINDOW_FIELDS = ['pengisian_mulai', 'pengisian_selesai'];

    /**
     * Pemanggil memasok tanggal valid YYYY-MM-DD serta metadata otoritatif yang terlindungi lock.
     * `batas_awal_pengisian` hanya diisi untuk jendela yang wajib diperiksa batas awalnya;
     * nilai null berarti periode belum bertanggal sehingga batasnya tidak dapat dihitung.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{urutan: int, batas_awal_pengisian?: ?string}>  $periodeMetadata
     */
    public function validate(array $data, array $periodeMetadata): void
    {
        $this->validateSelectedMetadata($data, $periodeMetadata);
        $errors = $this->batasAwalErrors($data, $periodeMetadata) + $this->kalenderErrors($data);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Revisi jendela PIC di dalam sesi koreksi: tahun pelaporan, tumpang tindih antarperiode, dan penutupan tidak
     * diperiksa karena jendela dibuka setelah penutupan; sebagai gantinya jendela yang berubah wajib termasuk lingkup
     * dan seluruh tanggalnya berada di dalam rentang tanggal sesi. Batas awal Q70 tetap berlaku.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{urutan: int, batas_awal_pengisian?: ?string}>  $periodeMetadata
     * @param  list<string>  $diubah  periode_id yang tanggal jendelanya berubah
     * @param  list<string>  $lingkup  `lingkup_koreksi.periode_ids`
     * @param  array{0: string, 1: string}  $rentangSesi
     */
    public function validateSesiKoreksi(array $data, array $periodeMetadata, array $diubah, array $lingkup, array $rentangSesi): void
    {
        $errors = $this->batasAwalErrors($data, $periodeMetadata);
        [$mulaiSesi, $akhirSesi] = $rentangSesi;
        $rentang = implode('–', array_map(fn (string $tanggal): string => CarbonImmutable::parse($tanggal)->locale('id')->translatedFormat('j F Y'), $rentangSesi));
        foreach ($data['periode'] as $index => $window) {
            if (! in_array($window['periode_id'], $diubah, true)) {
                continue;
            }
            if (! in_array($window['periode_id'], $lingkup, true)) {
                $errors["periode.$index.periode_id"] = 'Periode ini di luar lingkup sesi koreksi; jendelanya tidak dapat diubah.';

                continue;
            }
            foreach (self::WINDOW_FIELDS as $field) {
                if ($window[$field] < $mulaiSesi || $window[$field] > $akhirSesi) {
                    $errors["periode.$index.$field"] ??= "Tanggal harus berada di dalam sesi koreksi ($rentang).";
                }
            }
            if ($window['pengisian_mulai'] > $window['pengisian_selesai']) {
                $errors["periode.$index.pengisian_selesai"] ??= 'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{urutan: int, batas_awal_pengisian?: ?string}>  $periodeMetadata
     * @return array<string, string>
     */
    private function batasAwalErrors(array $data, array $periodeMetadata): array
    {
        $errors = [];
        foreach ($data['periode'] as $index => $window) {
            $metadata = $periodeMetadata[$window['periode_id']];
            if (! array_key_exists('batas_awal_pengisian', $metadata)) {
                continue;
            }
            $batas = $metadata['batas_awal_pengisian'];
            if ($batas === null) {
                $errors["periode.$index.periode_id"] = 'Lengkapi tanggal mulai dan selesai periode ini di Master Periode sebelum menyusun jadwal.';
            } elseif ($window['pengisian_mulai'] < $batas) {
                $tanggal = CarbonImmutable::parse($batas)->locale('id')->translatedFormat('j F Y');
                $errors["periode.$index.pengisian_mulai"] = "Jendela pengisian paling cepat dibuka $tanggal, tanggal 1 bulan terakhir periode.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function kalenderErrors(array $data): array
    {
        $errors = [];
        $year = (int) $data['tahun'];
        foreach ($data['periode'] as $index => $window) {
            foreach (self::WINDOW_FIELDS as $position => $field) {
                $date = $window[$field];
                if ($position > 0 && $window[self::WINDOW_FIELDS[$position - 1]] > $date) {
                    $errors["periode.$index.$field"] = 'Tanggal harus sama dengan atau setelah tanggal jendela sebelumnya.';
                }
            }
        }
        // Bandingkan tanggal, bukan nomor urutan: nomor tampilan tidak menentukan kronologi pengisian.
        $windows = $data['periode'];
        uasort($windows, fn (array $a, array $b): int => strcmp($a['pengisian_mulai'], $b['pengisian_mulai']));
        $previousEnd = null;
        foreach ($windows as $index => $window) {
            if ($previousEnd !== null && $window['pengisian_mulai'] <= $previousEnd) {
                $errors["periode.$index.pengisian_mulai"] = 'Jendela pengisian antarperiode tidak boleh tumpang tindih, termasuk pada hari yang sama.';
            }
            $previousEnd = max($previousEnd ?? $window['pengisian_selesai'], $window['pengisian_selesai']);
        }
        $closeYear = (int) substr($data['penutupan'], 0, 4);
        if ($closeYear < $year || $closeYear > $year + 1) {
            $errors['penutupan'] = 'Penutupan harus berada pada tahun pelaporan atau tahun berikutnya.';
        }
        if ($data['penutupan'] <= max(array_column($windows, 'pengisian_selesai'))) {
            $errors['penutupan'] = 'Penutupan paling cepat hari berikutnya setelah jendela pengisian terakhir selesai.';
        }

        return $errors;
    }

    /**
     * Mutasi urutan master hanya memeriksa aturan yang dipengaruhinya.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{urutan: int}>  $periodeMetadata
     */
    public function validateSelectedMetadata(array $data, array $periodeMetadata): void
    {
        $errors = [];
        $orders = [];
        foreach ($data['periode'] as $index => $window) {
            $order = $periodeMetadata[$window['periode_id']]['urutan'];
            if (isset($orders[$order])) {
                $errors["periode.$index.periode_id"] = 'Urutan periode yang dipilih harus berbeda dalam satu jadwal.';
            }
            $orders[$order] = true;
        }
        $year = (int) $data['tahun'];
        $lastOrder = max(array_keys($orders));
        foreach ($data['periode'] as $index => $window) {
            $isLast = $periodeMetadata[$window['periode_id']]['urutan'] === $lastOrder;
            foreach (self::WINDOW_FIELDS as $field) {
                $dateYear = (int) substr($window[$field], 0, 4);
                if ($dateYear < $year || $dateYear > $year + ($isLast ? 1 : 0)) {
                    $errors["periode.$index.$field"] = 'Tanggal harus berada pada tahun pelaporan; hanya periode terakhir boleh memasuki tahun berikutnya.';
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
