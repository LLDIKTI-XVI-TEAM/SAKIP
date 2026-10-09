<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\Perencanaan\IndikatorPresenter;

class ShowIndikator
{
    public function __construct(private readonly IndikatorPresenter $presenter) {}

    /**
     * Menyusun payload halaman detail satu indikator. Otorisasi `view` tetap di controller;
     * bentuk indikator, capability, gerbang regulasi, dan PJ efektif sama dengan halaman daftar
     * lewat IndikatorPresenter. Opsi sasaran/unit/regulasi hanya dikirim bila aktor boleh
     * mengubah indikator, karena hanya modal ubah di halaman ini yang memakainya (props minimum, Standards §2).
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, IndikatorKinerja $indikator): array
    {
        $can = $this->presenter->capabilities($user);
        $indikator->load([...$this->presenter->indikatorRelations($can['regulasi_read']), 'sasaranStrategis.renstra:id,kode,nama,tahun_mulai,tahun_selesai,is_aktif']);
        $sasaran = $indikator->sasaranStrategis;
        $renstra = $sasaran->renstra;
        $pic = $this->presenter->pjEfektif($can['penanggung_jawab_update'], [$indikator->id])->get($indikator->id)?->pic;
        $bolehUbah = $can['indikator_update'];

        return [
            'indikator' => $this->presenter->presentIndikator($indikator, $can['regulasi_read'], $pic),
            'sasaran' => $sasaran->only(['id', 'kode', 'deskripsi']),
            'renstra' => $renstra->only(['id', 'kode', 'nama', 'tahun_mulai', 'tahun_selesai', 'is_aktif']),
            'jumlahKomponenAktif' => $can['komponen_read'] ? $indikator->komponen()->where('aktif', true)->count() : null,
            'sasarans' => $bolehUbah
                ? SasaranStrategis::where('renstra_id', $renstra->id)
                    ->orderBy('urutan')
                    ->orderBy('kode')
                    ->get(['id', 'renstra_id', 'kode', 'deskripsi', 'urutan'])
                    ->map(fn (SasaranStrategis $item) => [...$item->only(['id', 'renstra_id', 'kode', 'deskripsi', 'urutan']), 'indikator_kinerjas' => []])
                : [],
            ...($bolehUbah ? $this->presenter->formOptions($can['regulasi_read']) : ['units' => [], 'regulasis' => []]),
            'can' => $can,
        ];
    }
}
