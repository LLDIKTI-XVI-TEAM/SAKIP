<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use App\Models\Renstra;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IndexRenstra extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', Renstra::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                Renstra::STATUS_DRAFT,
                Renstra::STATUS_AKTIF,
                Renstra::STATUS_NONAKTIF,
                Renstra::STATUS_DIARSIPKAN,
            ])],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? null;

        $user = $request->user();
        $dapatBacaRegulasi = $user !== null && $user->can('viewAny', Regulasi::class);
        $dapatBacaLampiran = $user !== null
            && app(PermissionResolver::class)->resolve($user, PermissionCodes::BERKAS_READ)->allowed;
        $dapatHapusRenstra = $user !== null && $user->can('delete', Renstra::class);
        $dapatHapusLampiran = $user !== null && $user->can('deleteAttachment', Renstra::class);

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

        if ($search !== '') {
            $query->where(function ($sq) use ($search) {
                $sq->where('nama', 'ilike', "%{$search}%")
                    ->orWhere('kode', 'ilike', "%{$search}%")
                    ->orWhere('deskripsi', 'ilike', "%{$search}%")
                    ->orWhere('dasar_hukum', 'ilike', "%{$search}%");
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $renstra = $query
            ->orderByDesc('tahun_mulai')
            ->orderBy('kode')
            ->paginate(15)
            ->withQueryString();

        $renstra->getCollection()->each(function (Renstra $item) use ($dapatBacaRegulasi, $dapatBacaLampiran, $dapatHapusRenstra, $dapatHapusLampiran): void {
            $item->setAttribute('can_delete', $dapatHapusRenstra
                && $item->status === Renstra::STATUS_DRAFT
                && ((int) $item->berkas_count === 0 || $dapatHapusLampiran));

            if (! $dapatBacaRegulasi) {
                $item->setAttribute('regulasi_id', null);
            }
            if (! $dapatBacaLampiran) {
                $item->setAttribute('berkas_count', null);
            }
        });

        return Inertia::render('Renstra/Index', [
            'renstra' => $renstra,
            'regulasiPilihan' => $regulasiPilihan,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'can' => [
                'create' => $user->can('create', Renstra::class),
                'update' => $user->can('update', Renstra::class),
                'delete' => $dapatHapusRenstra,
                'renstra:create' => $user->can('create', Renstra::class),
                'renstra:update' => $user->can('update', Renstra::class),
                'renstra:delete' => $dapatHapusRenstra,
                'berkas:delete' => $dapatHapusLampiran,
                'uploadAttachment' => $user !== null && $user->can('uploadAttachment', Renstra::class),
                'readRegulasi' => $dapatBacaRegulasi,
            ],
        ]);
    }
}
