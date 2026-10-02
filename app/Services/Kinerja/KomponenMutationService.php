<?php

namespace App\Services\Kinerja;

use App\Models\IndikatorKomponen;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Layanan mutasi create-komponen yang dipakai bersama jalur normal dan transisi formula atomik.
 *
 * Menyatukan normalisasi (trim kode/label/satuan, bawaan aktif true), pemeriksaan
 * sintaks bobot penyebut, snapshot audit bobot-eksak-string, dan pemetaan pelanggaran
 * unique menjadi pesan kode agar kedua jalur tidak drift.
 *
 * Penentu akhir validitas domain tetap `IndikatorPerhitunganService::validateDefinisiKomponen`
 * di batas mutasi masing-masing; layanan ini tidak menilai kecukupan komposisi akhir.
 */
class KomponenMutationService
{
    /**
     * Normalisasi satu baris input komponen sebelum disimpan atau dinilai sebagai kandidat.
     *
     * @param  array<string, mixed>  $item
     * @return array{kode: string, label: string, peran: mixed, bobot: mixed, urutan: int, satuan: ?string, aktif: bool}
     */
    public function normalisasiInput(array $item): array
    {
        $kode = trim((string) ($item['kode'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));

        $satuan = null;
        if (isset($item['satuan']) && $item['satuan'] !== null) {
            $satuanTrim = trim((string) $item['satuan']);
            $satuan = $satuanTrim !== '' ? $satuanTrim : null;
        }

        return [
            'kode' => $kode,
            'label' => $label,
            'peran' => $item['peran'] ?? null,
            'bobot' => $item['bobot'] ?? 0,
            'urutan' => (int) ($item['urutan'] ?? 1),
            'satuan' => $satuan,
            'aktif' => array_key_exists('aktif', $item) ? (bool) $item['aktif'] : true,
        ];
    }

    /**
     * Atribut siap simpan untuk satu baris komponen.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function atributCreate(string $indikatorId, array $item, string $createdBy): array
    {
        return array_merge($this->normalisasiInput($item), [
            'indikator_id' => $indikatorId,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Model kandidat in-memory untuk penilaian komposisi akhir sebelum mutasi apa pun.
     *
     * @param  array<string, mixed>  $item
     */
    public function modelKandidat(string $indikatorId, array $item): IndikatorKomponen
    {
        return new IndikatorKomponen($this->atributCreate($indikatorId, $item, ''));
    }

    /**
     * Membuat satu baris komponen tervalidasi sintaks di dalam transaksi pemanggil.
     *
     * Pemanggil tetap memegang kunci baris induk dan urutan kunci global; metode ini
     * tidak membuka transaksi sendiri agar tidak memecah atomicity.
     *
     * @param  array<string, mixed>  $item
     */
    public function buat(string $indikatorId, array $item, string $createdBy): IndikatorKomponen
    {
        $normal = $this->normalisasiInput($item);
        $this->pastikanBobotPenyebutValid($normal);

        $komponen = IndikatorKomponen::create(array_merge($normal, [
            'indikator_id' => $indikatorId,
            'created_by' => $createdBy,
        ]));

        $segar = $komponen->fresh();

        return $segar ?? $komponen;
    }

    /**
     * Memastikan komponen penyebut memiliki bobot lebih besar dari nol.
     *
     * Cermin aturan sintaks pada kedua FormRequest agar pesan identik di semua jalur.
     *
     * @param  array<string, mixed>  $item
     */
    public function pastikanBobotPenyebutValid(array $item, string $key = 'bobot'): void
    {
        if (($item['peran'] ?? null) !== 'penyebut') {
            return;
        }

        $bobot = isset($item['bobot']) ? (float) $item['bobot'] : 0.0;
        if ($bobot <= 0 || round($bobot, 12) <= 0) {
            throw ValidationException::withMessages([
                $key => $this->pesanBobotPenyebut(),
            ]);
        }
    }

    /**
     * Pesan tunggal untuk kode komponen yang sudah dipakai pada indikator yang sama.
     */
    public function pesanKodeDuplikat(): string
    {
        return 'Kode komponen sudah digunakan pada indikator ini.';
    }

    /**
     * Pesan tunggal untuk bobot penyebut yang tidak positif.
     */
    public function pesanBobotPenyebut(): string
    {
        return 'Bobot untuk komponen dengan peran penyebut wajib lebih besar dari 0.';
    }

    /**
     * Membentuk snapshot audit dengan bobot eksak sebagai string tanpa pembulatan biner.
     *
     * @return array<string, mixed>
     */
    public function formatAuditSnapshot(IndikatorKomponen $komponen): array
    {
        $snapshot = $komponen->toArray();
        $rawBobot = $komponen->getRawOriginal('bobot');
        if ($rawBobot !== null && $rawBobot !== '') {
            $snapshot['bobot'] = (string) $rawBobot;
        } elseif (isset($snapshot['bobot'])) {
            $snapshot['bobot'] = (string) $snapshot['bobot'];
        }

        return $snapshot;
    }

    /**
     * Mengecek pelanggaran unique constraint pada kode komponen lintas driver basis data.
     */
    public function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $errorCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        return $sqlState === '23505'
            || $errorCode === 1062
            || $errorCode === 19
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
