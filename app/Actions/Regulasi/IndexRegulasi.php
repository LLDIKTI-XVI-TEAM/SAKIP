<?php

namespace App\Actions\Regulasi;

use App\Models\Regulasi;

class IndexRegulasi
{
    /**
     * Daftar regulasi terbaru per tahun dengan pencarian nomor/tentang dan filter status aktif; proyeksi ringkas tanpa catatan.
     *
     * @return array<string, mixed>
     */
    public function handle(string $search, ?string $status): array
    {
        $regulasi = Regulasi::query()
            ->with('pembuat:id,nama')
            ->withCount('berkas')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereLike('nomor', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('tentang', "%{$search}%", caseSensitive: false);
                });
            })
            ->when($status !== null, fn ($query) => $query->where('aktif', $status === 'aktif'))
            ->orderByDesc('tahun')
            ->orderBy('jenis')
            ->orderBy('nomor')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Regulasi $item) => [
                'id' => $item->id,
                'jenis' => $item->jenis,
                'nomor' => $item->nomor,
                'tahun' => $item->tahun,
                'tentang' => $item->tentang,
                'tanggal' => $item->tanggal?->format('Y-m-d'),
                'tautan_sumber' => $item->tautan_sumber,
                'aktif' => $item->aktif,
                'berkas_count' => $item->berkas_count,
                'pembuat' => $item->pembuat?->nama,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ]);

        return [
            'regulasi' => $regulasi,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ];
    }
}
