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
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $regulasi = Regulasi::query()
            ->with('pembuat:id,name')
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
                'pembuat' => $item->pembuat?->name,
                'updated_at' => $item->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Regulasi/Index', [
            'regulasi' => $regulasi,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
        ]);
    }
}
