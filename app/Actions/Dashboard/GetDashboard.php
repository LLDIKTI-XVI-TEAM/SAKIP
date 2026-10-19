<?php

namespace App\Actions\Dashboard;

use App\Models\User;

class GetDashboard
{
    public function __construct(private ResolveDashboardKonteks $konteks, private StatusIndikatorDashboard $status, private ProgresRencanaAksiDashboard $rencanaAksi) {}

    /**
     * Ringkasan dibatasi unit yang boleh dibaca; nilai lintas satuan tidak dirata-ratakan.
     *
     * @param  array<string, mixed>  $filter  query tervalidasi IndexDashboardRequest
     */
    public function handle(User $actor, array $filter): array
    {
        $today = today(config('app.business_timezone'))->toDateString();
        $konteks = $this->konteks->handle($actor, $filter, $today);
        [$renstra, $jadwal, $periode] = [$konteks['renstra'], $konteks['jadwal'], $konteks['periodeJadwal']];

        return [
            'tahun' => $konteks['tahun'],
            'filter' => $konteks['filter'],
            'pilihan' => $konteks['pilihan'],
            'activeRenstra' => $renstra?->only(['id', 'nama', 'tahun_mulai', 'tahun_selesai']),
            'activeJadwal' => $jadwal?->only(['tahun', 'status']),
            'activePeriode' => $periode ? [
                'id' => $periode->periode_id,
                'nama_periode' => $periode->periode->nama.' '.$jadwal->tahun,
                'status' => $konteks['periodeAktif'] ? 'aktif' : 'terakhir',
            ] : null,
            ...$this->status->handle($actor, $konteks, $today),
            'progresRencanaAksi' => $this->rencanaAksi->handle($konteks),
        ];
    }
}
