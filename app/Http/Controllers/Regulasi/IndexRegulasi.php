<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexRegulasi extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Regulasi::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'per_page' => ['nullable', 'integer', 'in:10,20,25,50,100'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;
        $perPage = (int) ($filters['per_page'] ?? 10);

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
            ->paginate($perPage)
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
                'catatan' => $item->catatan,
                'versi' => $item->versi,
                'berkas_count' => $item->berkas_count,
                'pembuat' => $item->pembuat?->nama,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Regulasi/Index', [
            'regulasi' => $regulasi,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }
}
