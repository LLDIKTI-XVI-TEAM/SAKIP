<?php

namespace App\Services\Pengingat;

use App\Models\IndikatorKinerja;
use App\Services\Jadwal\SesiKoreksiJadwal;
use App\Services\Pengukuran\DaftarKewajibanPengukuran;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun kandidat pengingat tenggat pengisian bagi PIC yang pekerjaannya belum
 * selesai pada satu tanggal bisnis. Fase ini murni baca: tidak menulis outbox,
 * tidak mengirim notifikasi, dan tidak menilai hak isi akun PIC. Pemilihan
 * penerima serta pembatalan baris outbox tetap menjadi tanggung jawab lapisan
 * dispatch.
 */
final class KandidatPengingatTenggat
{
    public function __construct(
        private PengingatKontekstual $pengingat,
        private DaftarKewajibanPengukuran $kewajiban,
    ) {}

    /**
     * Evaluasi kandidat pengingat pada satu instan waktu; tanggal bisnis diturunkan dari instan tersebut.
     * Tanpa tulis, tanpa audit, tanpa ACL aktor.
     *
     * @param  CarbonImmutable  $tanggal  Instan asli; jamnya dipakai untuk aturan sesi, tanggalnya untuk H
     * @param  list<int>  $hMinus  daftar H-minus yang memicu pengingat (mis. [7,3,1])
     * @return array{tanggal:string, diperiksa:int, kandidat:list<array{periode_jadwal_id:string,user_id:string,h:int,jatuh_tempo:string,jumlah:int,indikator:list<array{indikator_id:string,kode:string,nama:string}>}>}
     */
    public function untukTanggal(CarbonImmutable $tanggal, array $hMinus): array
    {
        // Aturan sesi koreksi/penutupan dinilai pada instan asli (termasuk jam), sedangkan
        // tanggal bisnis 00:00 WITA hanya dipakai untuk tanggal hasil, selisih H, dan query kewajiban.
        $periode = $this->pengingat->periode($tanggal);
        $tanggalBisnis = $tanggal->setTimezone(config('app.business_timezone'))->startOfDay();
        $hPerPeriode = [];

        // Selisih hari dihitung terhadap tanggal bisnis eksplisit; hanya H-minus
        // yang terdaftar yang dievaluasi, tanpa perlakuan khusus H-0 atau H negatif.
        // Jatuh tempo diparse pada zona bisnis agar diff tidak bergeser offset.
        foreach ($periode as $p) {
            $jatuhTempo = CarbonImmutable::parse($p->pengisian_selesai->toDateString(), config('app.business_timezone'));
            $h = (int) $tanggalBisnis->diffInDays($jatuhTempo, false);
            if (in_array($h, $hMinus, true)) {
                $hPerPeriode[$p->id] = $h;
            }
        }

        $hasil = ['tanggal' => $tanggalBisnis->toDateString(), 'diperiksa' => $periode->count(), 'kandidat' => []];
        if ($hPerPeriode === []) {
            return $hasil;
        }

        $periodeById = $periode->keyBy('id');
        // Scheduler berjalan tanpa aktor, sehingga tidak ada unit yang perlu ditolak di lapisan ini.
        $denyKosong = DB::table('unit')->select('id')->whereRaw('1 = 0');
        $baris = $this->kewajiban->query(array_keys($hPerPeriode), $denyKosong, $tanggalBisnis->toDateString())
            ->select(['k.periode_jadwal_id', 'pic.user_id', 'k.indikator_id', 'i.kode', 'cs.nama'])
            ->whereNotNull('pic.user_id')
            ->where('k.berlaku', true)
            ->where('u.status', 'aktif')
            ->where('i.status', '!=', IndikatorKinerja::STATUS_ARSIP)
            ->where(fn ($q) => $q->whereNull('h.id')->orWhereIn('h.status_alur', ['draft', 'dikembalikan']))
            ->reorder()
            ->orderBy('k.periode_jadwal_id')
            ->orderBy('pic.user_id')
            ->orderBy('i.kode')
            ->orderBy('k.indikator_id')
            ->get();

        // Tahun yang dibuka kembali lewat sesi koreksi hanya membuka indikator di dalam
        // `lingkup_koreksi`; baris di luar lingkup tetap terkunci bagi PIC sehingga bukan kandidat.
        // Aturan sesi dinilai pada instan asli, sejalan dengan penyaringan periode di atas.
        $baris = $baris->filter(function ($r) use ($periodeById, $tanggal) {
            $p = $periodeById[$r->periode_jadwal_id];
            if (! $p->jadwal->tahunDitutup($tanggal)) {
                return true;
            }

            return SesiKoreksiJadwal::berlaku($p->jadwal, 'pengukuran', $r->indikator_id, [$p->periode_id], $tanggal);
        })->values();

        // Satu entri per periode × PIC; indikator tetap mengikuti urutan query.
        foreach ($baris->groupBy(fn ($r) => $r->periode_jadwal_id.'|'.$r->user_id) as $kelompok) {
            $pertama = $kelompok->first();
            $p = $periodeById[$pertama->periode_jadwal_id];
            $hasil['kandidat'][] = [
                'periode_jadwal_id' => $pertama->periode_jadwal_id,
                'user_id' => $pertama->user_id,
                'h' => $hPerPeriode[$pertama->periode_jadwal_id],
                'jatuh_tempo' => $p->pengisian_selesai->toDateString(),
                'jumlah' => $kelompok->count(),
                'indikator' => $kelompok->map(fn ($r) => [
                    'indikator_id' => $r->indikator_id,
                    'kode' => $r->kode,
                    'nama' => $r->nama,
                ])->values()->all(),
            ];
        }

        return $hasil;
    }
}
