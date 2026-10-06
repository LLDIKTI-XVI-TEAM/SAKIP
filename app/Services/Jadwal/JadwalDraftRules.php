<?php

namespace App\Services\Jadwal;

use Illuminate\Validation\ValidationException;

/**
 * Memusatkan validasi aturan kalender yang digunakan bersama oleh Action
 * SaveJadwalDraft dan SavePeriode agar aturan keduanya konsisten.
 *
 * Action tetap menangani use case secara utuh: otorisasi, pengambilan
 * data, locking, transaksi, penyimpanan, dan audit. Service ini hanya
 * memvalidasi data yang diberikan Action dan melempar ValidationException
 * jika aturan dilanggar.
 */
class JadwalDraftRules
{
    public const WINDOW_FIELDS = ['pengisian_mulai', 'pengisian_selesai', 'reviu_mulai', 'reviu_selesai'];

    /**
     * Pemanggil memasok tanggal valid YYYY-MM-DD serta metadata otoritatif yang terlindungi lock.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, array{urutan: int}>  $periodeMetadata
     */
    public function validate(array $data, array $periodeMetadata): void
    {
        $this->validateSelectedMetadata($data, $periodeMetadata);
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
        foreach (['rencana_aksi_mulai', 'rencana_aksi_selesai'] as $field) {
            if ((int) substr($data[$field], 0, 4) !== $year) {
                $errors[$field] = 'Jendela Rencana Aksi harus berada pada tahun pelaporan.';
            }
        }
        if ($data['rencana_aksi_mulai'] > $data['rencana_aksi_selesai']) {
            $errors['rencana_aksi_selesai'] = 'Selesai Rencana Aksi harus sama dengan atau setelah tanggal mulai.';
        }
        if ($data['rencana_aksi_selesai'] >= min(array_column($windows, 'pengisian_mulai'))) {
            $errors['rencana_aksi_selesai'] = 'Rencana Aksi harus selesai sebelum pengisian pertama dimulai.';
        }
        $closeYear = (int) substr($data['penutupan'], 0, 4);
        if ($closeYear < $year || $closeYear > $year + 1) {
            $errors['penutupan'] = 'Penutupan harus berada pada tahun pelaporan atau tahun berikutnya.';
        }
        if ($data['penutupan'] <= max(array_column($windows, 'reviu_selesai'))) {
            $errors['penutupan'] = 'Penutupan paling cepat hari berikutnya setelah seluruh target selesai review.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Mutasi urutan master hanya memeriksa aturan yang dipengaruhinya, bukan mengharuskan RA nullable existing dilengkapi.
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
