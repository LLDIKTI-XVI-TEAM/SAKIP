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
        $dapatUbahRenstra = $user !== null && $user->can('update', Renstra::class);
        $dapatHapusRenstra = $user !== null && $user->can('delete', Renstra::class);
        $dapatHapusLampiran = $user !== null && $user->can('deleteAttachment', Renstra::class);

        $regulasiPilihan = $dapatBacaRegulasi
            ? Regulasi::query()
                ->where('aktif', true)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang'])
            : [];

        $withRelations = $dapatBacaRegulasi ? ['regulasi:id,nomor'] : [];

        $query = Renstra::query()
            ->with($withRelations)
            ->withCount('berkas');

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

        $renstra->through(fn (Renstra $item): array => [
            'id' => $item->id,
            'nama' => $item->nama,
            'kode' => $item->kode,
            'tahun_mulai' => $item->tahun_mulai,
            'tahun_selesai' => $item->tahun_selesai,
            'status' => $item->status,
            'is_aktif' => $item->is_aktif,
            'regulasi_id' => $dapatBacaRegulasi ? $item->regulasi_id : null,
            'regulasi_nomor' => $dapatBacaRegulasi ? $item->regulasi?->nomor : null,
            'berkas_count' => $dapatBacaLampiran ? (int) $item->berkas_count : null,
            'can_update' => $dapatUbahRenstra && in_array($item->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true),
            'can_delete' => $dapatHapusRenstra && $item->status === Renstra::STATUS_DRAFT
                && ($dapatHapusLampiran || ($dapatBacaLampiran && (int) $item->berkas_count === 0)),
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ]);

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
