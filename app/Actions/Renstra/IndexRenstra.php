<?php

namespace App\Actions\Renstra;

use App\Models\Regulasi;
use App\Models\Renstra;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;

class IndexRenstra
{
    public function __construct(private PermissionResolver $resolver) {}

    /**
     * Daftar Renstra dengan pencarian/filter status dan capability per baris. Regulasi dan hitungan lampiran hanya
     * dikirim bila aktor boleh membacanya; hapus mengikuti status draft dan kewenangan lampiran.
     *
     * @return array<string, mixed>
     */
    public function handle(User $user, string $search, ?string $status): array
    {
        $dapatBacaRegulasi = $user->can('viewAny', Regulasi::class);
        $dapatBacaLampiran = $this->resolver->resolve($user, PermissionCodes::BERKAS_READ)->allowed;
        $dapatBuatRenstra = $user->can('create', Renstra::class);
        $dapatUbahRenstra = $user->can('update', Renstra::class);
        $dapatHapusRenstra = $user->can('delete', Renstra::class);
        $dapatHapusLampiran = $user->can('deleteAttachment', Renstra::class);

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

        return [
            'renstra' => $renstra,
            'regulasiPilihan' => $regulasiPilihan,
            'filters' => [
                'q' => $search,
                'status' => $status,
            ],
            'can' => [
                'create' => $dapatBuatRenstra,
                'update' => $dapatUbahRenstra,
                'delete' => $dapatHapusRenstra,
                'renstra:create' => $dapatBuatRenstra,
                'renstra:update' => $dapatUbahRenstra,
                'renstra:delete' => $dapatHapusRenstra,
                'berkas:delete' => $dapatHapusLampiran,
                'uploadAttachment' => $user->can('uploadAttachment', Renstra::class),
                'readRegulasi' => $dapatBacaRegulasi,
            ],
        ];
    }
}
