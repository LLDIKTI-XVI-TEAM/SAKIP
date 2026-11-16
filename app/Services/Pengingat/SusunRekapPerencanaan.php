<?php

namespace App\Services\Pengingat;

use App\Models\IndikatorKinerja;
use App\Models\PeriodeJadwal;
use App\Services\Jadwal\SesiKoreksiJadwal;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Menyusun rekap progres pengisian bagi penerima Perencanaan pada H-3 dan H-1.
 *
 * Murni baca: tidak menulis outbox, tidak memilih penerima, dan tidak mengirim notifikasi.
 * Jendela dan kewajiban memakai sumber yang sama dengan pengingat tenggat PIC, tetapi indikator
 * tanpa PJ ikut dihitung karena rekap menggambarkan seluruh kewajiban, bukan pekerjaan satu PIC.
 */
final class SusunRekapPerencanaan
{
    private const MAKS_UNIT = 10;

    public function __construct(private PengingatKontekstual $pengingat, private DaftarKewajibanPengukuran $kewajiban) {}

    /**
     * Jendela berjalan yang tenggatnya tepat H hari lagi dari tanggal bisnis `$saat`.
     *
     * @param  list<int>  $hMinus
     * @return list<array{periode: PeriodeJadwal, h: int}>
     */
    public function periodeUntuk(CarbonImmutable $saat, array $hMinus): array
    {
        $hasil = [];
        foreach ($this->pengingat->periode($saat) as $periode) {
            $h = self::sisaHari($periode, $saat);
            if (in_array($h, $hMinus, true)) {
                $hasil[] = ['periode' => $periode, 'h' => $h];
            }
        }

        return $hasil;
    }

    /** Selisih hari bisnis sampai `pengisian_selesai`; negatif berarti tenggat sudah lewat. */
    public static function sisaHari(PeriodeJadwal $periode, CarbonImmutable $saat): int
    {
        $zona = config('app.business_timezone');

        return (int) $saat->setTimezone($zona)->startOfDay()->diffInDays(CarbonImmutable::parse($periode->pengisian_selesai->toDateString(), $zona), false);
    }

    /**
     * Parameter pesan rekap satu jendela bagi satu penerima; seluruh nilai skalar agar dapat dibekukan di outbox.
     *
     * @param  Builder  $unitDitolak  subquery unit yang ditolak baca bagi penerima
     * @return array{h:int, jatuh_tempo:string, jatuh_tempo_tampil:string, periode:string, tahun:int, belum_mengisi:int, draf:int, dikembalikan:int, diajukan:int, belum_selesai:int, total:int, rincian_unit:string, url:string}
     */
    public function parameter(PeriodeJadwal $periode, int $h, Builder $unitDitolak, CarbonImmutable $saat): array
    {
        $periode->loadMissing(['jadwal', 'periode']);
        $tanggalBisnis = $saat->setTimezone(config('app.business_timezone'))->toDateString();
        $baris = $this->kewajiban->query([$periode->id], $unitDitolak, $tanggalBisnis)
            ->where('k.berlaku', true)
            ->where('u.status', 'aktif')
            ->where('i.status', '!=', IndikatorKinerja::STATUS_ARSIP)
            ->reorder()
            ->select(['k.indikator_id', 'u.id as unit_id', 'u.nama as unit_nama', 'h.status_alur'])
            ->get();
        // Tahun yang dibuka kembali lewat sesi koreksi hanya mewajibkan indikator di dalam lingkupnya.
        if ($periode->jadwal->tahunDitutup($saat)) {
            $baris = $baris->filter(fn ($r): bool => SesiKoreksiJadwal::berlaku($periode->jadwal, 'pengukuran', $r->indikator_id, [$periode->periode_id], $saat))->values();
        }

        $belumMengisi = $baris->whereNull('status_alur')->count();
        $draf = $baris->where('status_alur', 'draft')->count();
        $dikembalikan = $baris->where('status_alur', 'dikembalikan')->count();
        $belumSelesai = $belumMengisi + $draf + $dikembalikan;
        $jatuhTempo = $periode->pengisian_selesai->toDateString();

        return [
            'h' => $h,
            'jatuh_tempo' => $jatuhTempo,
            'jatuh_tempo_tampil' => CarbonImmutable::parse($jatuhTempo, config('app.business_timezone'))->locale('id')->translatedFormat('j F Y'),
            'periode' => $periode->periode->nama,
            'tahun' => (int) $periode->jadwal->tahun,
            'belum_mengisi' => $belumMengisi,
            'draf' => $draf,
            'dikembalikan' => $dikembalikan,
            'diajukan' => $baris->count() - $belumSelesai,
            'belum_selesai' => $belumSelesai,
            'total' => $baris->count(),
            'rincian_unit' => $this->rincianUnit($baris->filter(fn ($r): bool => in_array($r->status_alur, [null, 'draft', 'dikembalikan'], true))),
            'url' => route('dashboard'),
        ];
    }

    /** Satu kalimat berisi jumlah belum selesai per unit; unit di luar batas hanya disebut jumlahnya. */
    /** @param  Collection<int, object>  $belumSelesai */
    private function rincianUnit(Collection $belumSelesai): string
    {
        if ($belumSelesai->isEmpty()) {
            return 'Semua indikator sudah diajukan.';
        }
        $unit = $belumSelesai->groupBy('unit_id')
            ->map(fn ($kelompok): array => ['nama' => $kelompok->first()->unit_nama, 'jumlah' => $kelompok->count()])
            ->sort(fn (array $a, array $b): int => [$b['jumlah'], $a['nama']] <=> [$a['jumlah'], $b['nama']])
            ->values();
        $bagian = $unit->take(self::MAKS_UNIT)->map(fn (array $u): string => $u['nama'].' '.$u['jumlah'])->all();
        if ($unit->count() > self::MAKS_UNIT) {
            $bagian[] = 'dan '.($unit->count() - self::MAKS_UNIT).' unit lain';
        }

        return 'Belum selesai per unit: '.implode('; ', $bagian).'.';
    }
}
