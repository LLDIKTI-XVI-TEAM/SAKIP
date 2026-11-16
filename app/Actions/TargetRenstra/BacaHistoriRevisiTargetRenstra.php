<?php

namespace App\Actions\TargetRenstra;

use App\Models\Renstra;
use App\Models\RevisiTargetRenstra;
use App\Models\RevisiTargetRenstraNilai;
use App\Models\User;
use App\Policies\TargetKinerjaPolicy;
use App\Support\TargetTahunanDecimal;

/**
 * Histori revisi bernomor per Renstra (Q71 §71.3). Sama seperti riwayat per indikator, histori adalah data target sehingga
 * cukup indikator:read; hanya field allowlist yang dikirim, tanpa email, dasar_izin, maupun draf yang belum disimpan.
 */
class BacaHistoriRevisiTargetRenstra
{
    // Batas 100 revisi terbaru; ganti ke pagination bila `terpotong` pernah terjadi.
    private const BATAS = 100;

    public function __construct(private readonly TargetKinerjaPolicy $policy) {}

    /** @return array{items: list<array{nomor: int, waktu: string, aktor: ?string, justifikasi: string, perubahan: list<array<string, mixed>>}>, terpotong: bool} */
    public function handle(User $actor, string $renstraId): array
    {
        // Izin diputuskan sebelum lookup agar keberadaan Renstra tidak bocor ke pengguna tanpa izin.
        $this->policy->view($actor)->authorize();
        $renstra = Renstra::whereKey($renstraId)->firstOrFail();

        $revisi = RevisiTargetRenstra::where('renstra_id', $renstra->id)->where('status', RevisiTargetRenstra::STATUS_DISIMPAN)
            ->with(['penyimpan:id,nama', 'nilai.indikator:id,kode,nama,desimal_tampilan'])->orderByDesc('nomor')->limit(self::BATAS + 1)->get();

        return [
            'items' => $revisi->take(self::BATAS)->map(fn (RevisiTargetRenstra $r): array => [
                'nomor' => $r->nomor,
                'waktu' => $r->disimpan_pada->toIso8601String(),
                'aktor' => $r->penyimpan?->nama,
                'justifikasi' => $r->justifikasi,
                'perubahan' => $r->nilai->sortBy(fn (RevisiTargetRenstraNilai $n): string => $n->indikator->kode.'|'.$n->tahun)->map(fn (RevisiTargetRenstraNilai $n): array => [
                    'indikator' => ['kode' => $n->indikator->kode, 'nama' => $n->indikator->nama, 'desimal_tampilan' => $n->indikator->desimal_tampilan],
                    'tahun' => $n->tahun,
                    'nilai_lama' => TargetTahunanDecimal::normalize($n->nilai_lama),
                    'nilai_baru' => TargetTahunanDecimal::normalize($n->nilai_baru),
                ])->values()->all(),
            ])->values()->all(),
            'terpotong' => $revisi->count() > self::BATAS,
        ];
    }
}
