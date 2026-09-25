<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IndexRenstra extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Renstra::class);

        $user = $request->user();
        $dapatBacaRegulasi = $user !== null && $user->can('viewAny', Regulasi::class);

        $regulasiPilihan = $dapatBacaRegulasi
            ? Regulasi::query()
                ->where('aktif', true)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang'])
            : [];

        $withRelations = $dapatBacaRegulasi ? ['regulasi'] : [];

        $query = Renstra::query()
            ->with($withRelations)
            ->withCount(['sasaranStrategis', 'berkas']);

        if ($q = $request->input('q')) {
            $query->where(function ($sq) use ($q) {
                $sq->where('nama', 'ilike', "%{$q}%")
                    ->orWhere('kode', 'ilike', "%{$q}%")
                    ->orWhere('deskripsi', 'ilike', "%{$q}%")
                    ->orWhere('dasar_hukum', 'ilike', "%{$q}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $renstra = $query
            ->orderByDesc('tahun_mulai')
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Renstra/Index', [
            'renstra' => $renstra,
            'regulasiPilihan' => $regulasiPilihan,
            'filters' => [
                'q' => $request->input('q', ''),
                'status' => $request->input('status', ''),
            ],
            'can' => [
                'create' => $user->can('create', Renstra::class),
                'update' => $user->can('update', Renstra::class),
                'delete' => $user->can('delete', Renstra::class),
                'renstra:create' => $user->can('create', Renstra::class),
                'renstra:update' => $user->can('update', Renstra::class),
                'renstra:delete' => $user->can('delete', Renstra::class),
                'berkas:delete' => $user !== null && $user->can('deleteAttachment', Renstra::class),
            ],
        ]);
    }
}
