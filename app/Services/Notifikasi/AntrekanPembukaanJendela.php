<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Pengingat\PengingatKontekstual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Producer broadcast pembukaan satu jendela pengisian ke outbox eksternal, dipakai jalur aksi resmi
 * (listener `JendelaPengisianDibuka`) dan jalur tanggal (perintah harian). Kejadian memuat tanggal bisnis
 * pembukaan, sehingga kedua jalur pada hari yang sama menghasilkan kunci idempoten yang sama.
 * Penerima memakai aturan yang sama dengan item in-app; hak isi dinilai ulang pada gerbang kirim.
 */
final class AntrekanPembukaanJendela
{
    public function __construct(private readonly PenerimaNotifikasi $penerima, private readonly AntrekanNotifikasiEksternal $antrean, private readonly PengingatKontekstual $pengingat) {}

    /**
     * Jendela yang terbuka karena tanggal: `pengisian_mulai` sama dengan tanggal bisnis `$saat`. Syarat jadwal aktif,
     * tahun belum ditutup kecuali jendela PIC sesi koreksi, dan bukan periode lampau 2026 mengikuti pengingat in-app.
     * Sesi koreksi tanpa pengukuran tidak membuka pengisian bagi PIC, sama dengan jalur aksi. Murni baca.
     *
     * @return Collection<int, PeriodeJadwal>
     */
    public function dibukaPada(CarbonImmutable $saat): Collection
    {
        $tanggal = $saat->setTimezone(config('app.business_timezone'))->toDateString();

        return $this->pengingat->periode($saat)->filter(fn (PeriodeJadwal $jendela): bool => $jendela->pengisian_mulai->toDateString() === $tanggal
            && (! $jendela->jadwal->tahunDitutup($saat) || in_array('pengukuran', $jendela->jadwal->lingkup_koreksi['jenis_objek'], true)))->values();
    }

    /**
     * @param  list<string>|null  $indikatorIds  null = semua indikator; daftar = lingkup sesi koreksi
     * @return Collection<int, NotifikasiPengiriman>
     */
    public function antre(PeriodeJadwal $jendela, ?array $indikatorIds, string $tanggalBisnis): Collection
    {
        $jendela->loadMissing(['periode', 'jadwal']);

        return $this->antrean->antre(
            'jendela_dibuka:'.$tanggalBisnis,
            $jendela,
            (string) $jendela->periode_id,
            array_map(fn (User $user): string => $user->id, $this->penerima->pjJendela($jendela->id, $indikatorIds, $tanggalBisnis)),
            ['whatsapp', 'email'],
            config('notifikasi.template_pembukaan'),
            [
                'periode' => $jendela->periode->nama,
                'tahun' => $jendela->jadwal->tahun,
                'selesai' => $jendela->pengisian_selesai->locale('id')->translatedFormat('j F Y'),
                'tautan' => route('pengukuran.index'),
            ],
        );
    }
}
