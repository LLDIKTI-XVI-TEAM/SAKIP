<?php

namespace App\Services\Periode;

use App\Models\Periode;
use Illuminate\Validation\ValidationException;

/**
 * Aturan tanggal periode Q70 §70.1 bersama SavePeriode dan ReplaceFinalPeriode. Pemanggil memegang
 * Periode::lockConfiguration(exclusive: true) agar daftar periode aktif tidak berubah selama pemeriksaan.
 */
class TanggalPeriodeRules
{
    /** Batas hari tahun non-kabisat agar tanggal sah setiap tahun (29 Februari ditolak). */
    private const HARI_MAKS = [2 => 28, 4 => 30, 6 => 30, 9 => 30, 11 => 30];

    /** @param string|null $kunciGalat seluruh galat dipindah ke field ini dengan awalan nama peran periode (mis. pengganti nilai akhir) */
    public function validate(Periode $kandidat, ?string $kunciGalat = null, string $peran = ''): void
    {
        $galat = $this->galat($kandidat);
        if ($galat === []) {
            return;
        }
        if ($kunciGalat !== null) {
            $pesan = reset($galat);
            $galat = [$kunciGalat => $peran.': '.lcfirst($pesan)];
        }

        throw ValidationException::withMessages($galat);
    }

    /** @return array<string, string> */
    private function galat(Periode $kandidat): array
    {
        $kosong = array_values(array_filter(Periode::TANGGAL, fn (string $kolom): bool => $kandidat->$kolom === null));
        if ($kosong === Periode::TANGGAL) {
            return $kandidat->aktif ? ['bulan_mulai' => 'Tanggal mulai dan selesai wajib diisi untuk periode aktif.'] : [];
        }
        if ($kosong !== []) {
            return [$kosong[0] => 'Lengkapi tanggal mulai dan selesai, atau kosongkan keduanya.'];
        }
        foreach (['mulai', 'selesai'] as $ujung) {
            if ($kandidat->{"hari_$ujung"} > (self::HARI_MAKS[$kandidat->{"bulan_$ujung"}] ?? 31)) {
                return ["hari_$ujung" => 'Tanggal '.$ujung.' tidak tersedia pada bulan tersebut setiap tahun.'];
            }
        }
        [$mulai, $selesai] = $this->kunci($kandidat);
        if ($mulai > $selesai) {
            return ['hari_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'];
        }
        if (! $kandidat->aktif) {
            return [];
        }
        $lain = Periode::where('aktif', true)->whereNotNull('bulan_mulai')
            ->when($kandidat->getKey(), fn ($query, $id) => $query->whereKeyNot($id))
            ->orderBy('urutan')->orderBy('id')->get(['id', 'nama', ...Periode::TANGGAL]);
        foreach ($lain as $periode) {
            [$mulaiLain, $selesaiLain] = $this->kunci($periode);
            if ($mulai <= $selesaiLain && $mulaiLain <= $selesai) {
                return ['bulan_mulai' => 'Rentang tanggal tumpang tindih dengan periode aktif '.$periode->nama.'.'];
            }
        }

        return [];
    }

    /** @return array{int, int} bulan×100+hari agar urutan tanggal dalam setahun dapat dibandingkan langsung */
    private function kunci(Periode $periode): array
    {
        return [$periode->bulan_mulai * 100 + $periode->hari_mulai, $periode->bulan_selesai * 100 + $periode->hari_selesai];
    }
}
