<?php

namespace App\Services\Pengingat;

use Carbon\CarbonImmutable;

/**
 * Menyusun payload pengingat tenggat PIC dari hasil evaluator kandidat.
 *
 * Service ini hanya memetakan kandidat menjadi identitas kejadian dan parameter
 * pesan yang stabil; tidak membaca pengaturan, tidak menulis outbox, dan tidak
 * mengirim notifikasi. Penentuan kanal, template, serta idempotensi tetap milik
 * lapisan producer/dispatch.
 */
final class SusunPengingatTenggat
{
    public function __construct(private KandidatPengingatTenggat $kandidat) {}

    /**
     * @param  list<int>  $hMinus  daftar H-minus yang memicu pengingat
     * @return array{tanggal:string,diperiksa:int,pesan:list<array{periode_jadwal_id:string,user_id:string,h:int,jatuh_tempo:string,kejadian:string,konteks_periode:string,jumlah:int,parameter:array{h:int,jatuh_tempo:string,jatuh_tempo_tampil:string,jumlah:int,url:string}}>}
     */
    public function untukTanggal(CarbonImmutable $saat, array $hMinus): array
    {
        $hasil = $this->kandidat->untukTanggal($saat, $hMinus);

        return [
            'tanggal' => $hasil['tanggal'],
            'diperiksa' => $hasil['diperiksa'],
            'pesan' => array_map(fn (array $k): array => [
                'periode_jadwal_id' => $k['periode_jadwal_id'],
                'user_id' => $k['user_id'],
                'h' => $k['h'],
                'jatuh_tempo' => $k['jatuh_tempo'],
                'kejadian' => 'ews:h'.$k['h'],
                'konteks_periode' => $k['periode_jadwal_id'].':'.$k['jatuh_tempo'],
                'jumlah' => $k['jumlah'],
                'parameter' => [
                    'h' => $k['h'],
                    'jatuh_tempo' => $k['jatuh_tempo'],
                    'jatuh_tempo_tampil' => CarbonImmutable::parse($k['jatuh_tempo'], config('app.business_timezone'))->locale('id')->translatedFormat('j F Y'),
                    'jumlah' => $k['jumlah'],
                    'url' => route('pengukuran.index'),
                ],
            ], $hasil['kandidat']),
        ];
    }
}
