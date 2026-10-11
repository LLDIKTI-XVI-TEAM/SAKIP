<?php

namespace App\Actions\Kegiatan;

use App\Models\Kegiatan;
use App\Models\Periode;
use App\Models\Unit;
use App\Models\User;
use App\Policies\KegiatanPolicy;
use App\Services\Authorization\PermissionResolver;

class DaftarKegiatan
{
    public function __construct(private readonly PermissionResolver $resolver, private readonly KegiatanPolicy $policy) {}

    /** Baca global sesuai Q32, dengan deny unit di SQL dan capability ubah per objek. @param array<string,mixed> $filters @return array<string,mixed> */
    public function handle(User $actor, array $filters): array
    {
        abort_unless($this->policy->viewAny($actor), 403);
        $query = Kegiatan::with(['unit:id,nama', 'periode:id,nama'])->whereNotIn('unit_id', $this->resolver->unitDitolak($actor, 'kegiatan:read'));
        foreach (['tahun', 'unit_id', 'periode_id', 'status'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        $page = $query->orderByDesc('tahun')->orderByDesc('created_at')->orderBy('id')->paginate(20)->withQueryString();
        $units = Unit::whereNotIn('id', $this->resolver->unitDitolak($actor, 'kegiatan:read'))->orderBy('nama')->get(['id', 'nama']);
        $activeUnitIds = Unit::where('status', 'aktif')->pluck('id');
        $scopes = $activeUnitIds->merge($page->getCollection()->pluck('unit_id'))->unique()->values()->all();
        $decisions = $this->resolver->decideManyForUnits($actor, ['kegiatan:create', 'kegiatan:update'], $scopes);
        $page->through(fn (Kegiatan $kegiatan) => [
            'id' => $kegiatan->id, 'nama' => $kegiatan->nama, 'tujuan' => $kegiatan->tujuan, 'tahun' => $kegiatan->tahun, 'status' => $kegiatan->status,
            'tanggal_rencana' => $kegiatan->tanggal_rencana?->format('Y-m-d'), 'unit' => $kegiatan->unit->only(['id', 'nama']), 'periode' => $kegiatan->periode->only(['id', 'nama']),
            'can' => ['update' => $decisions[$kegiatan->unit_id]['kegiatan:update']['allowed']],
        ]);

        return [
            'kegiatan' => $page,
            'filters' => array_replace(['tahun' => '', 'unit_id' => '', 'periode_id' => '', 'status' => ''], array_intersect_key($filters, array_flip(['tahun', 'unit_id', 'periode_id', 'status']))),
            'unitOptions' => $units->map(fn (Unit $unit) => $unit->only(['id', 'nama']))->all(),
            'periodeOptions' => Periode::orderBy('urutan')->orderBy('id')->get(['id', 'nama'])->toArray(),
            'can' => ['create' => $activeUnitIds->contains(fn (string $unitId) => $decisions[$unitId]['kegiatan:create']['allowed'])],
        ];
    }
}
