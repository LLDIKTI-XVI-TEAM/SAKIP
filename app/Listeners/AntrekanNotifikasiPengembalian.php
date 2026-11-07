<?php

namespace App\Listeners;

use App\Events\PengukuranDibukaKembali;
use App\Events\PengukuranDikembalikan;
use App\Events\RencanaAksiDibukaKembali;
use App\Events\RencanaAksiDikembalikan;
use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use Illuminate\Support\Str;
use Throwable;

class AntrekanNotifikasiPengembalian
{
    public function __construct(private readonly AntrekanNotifikasiEksternal $antrean) {}

    public function handleRencanaAksiDikembalikan(RencanaAksiDikembalikan $event): void
    {
        $this->rencanaAksi($event, 'dikembalikan');
    }

    public function handleRencanaAksiDibukaKembali(RencanaAksiDibukaKembali $event): void
    {
        $this->rencanaAksi($event, 'dibuka_kembali');
    }

    public function handlePengukuranDikembalikan(PengukuranDikembalikan $event): void
    {
        $this->pengukuran($event, 'dikembalikan');
    }

    public function handlePengukuranDibukaKembali(PengukuranDibukaKembali $event): void
    {
        $this->pengukuran($event, 'dibuka_kembali');
    }

    private function rencanaAksi(RencanaAksiDikembalikan|RencanaAksiDibukaKembali $event, string $jenis): void
    {
        try {
            if ($event->penerimaId === null) {
                error_log('Notifikasi eksternal tanpa penerima: rencana_aksi '.$event->rencanaAksiId);

                return;
            }
            $ra = RencanaAksi::with('indikator')->findOrFail($event->rencanaAksiId);
            $this->antrean->antre('rencana_aksi.'.$jenis.':'.$event->versiPengajuanId, $ra, (string) $ra->tahun,
                [$event->penerimaId], ['whatsapp', 'email'], config('notifikasi.template_pengembalian'),
                ['judul' => 'Rencana Aksi '.$ra->indikator->kode.' '.$ra->tahun, 'alasan' => Str::limit(Str::squish($event->alasan), 199, '…'),
                    'url' => route('rencana-aksi.show', ['rencanaAksi' => $ra->id])]);
        } catch (Throwable $e) {
            error_log('Notifikasi eksternal gagal diantrekan: '.$e::class);
        }
    }

    private function pengukuran(PengukuranDikembalikan|PengukuranDibukaKembali $event, string $jenis): void
    {
        try {
            if ($event->penerimaId === null) {
                error_log('Notifikasi eksternal tanpa penerima: pengukuran '.$event->pengukuranId);

                return;
            }
            $p = PengukuranKinerja::with(['indikator', 'periode'])->findOrFail($event->pengukuranId);
            $this->antrean->antre('pengukuran.'.$jenis.':'.$event->versi, $p, (string) $p->periode_id,
                [$event->penerimaId], ['whatsapp', 'email'], config('notifikasi.template_pengembalian'),
                ['judul' => 'Pengukuran '.$p->indikator->kode.' '.$p->periode->nama.' '.$p->tahun,
                    'alasan' => Str::limit(Str::squish($event->alasan), 199, '…'), 'url' => route('pengukuran.edit', ['id' => $p->id])]);
        } catch (Throwable $e) {
            error_log('Notifikasi eksternal gagal diantrekan: '.$e::class);
        }
    }
}
