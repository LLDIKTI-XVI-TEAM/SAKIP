<?php

namespace App\Actions\Jadwal;

use App\Models\Periode;
use App\Models\Renstra;
use App\Models\User;
use App\Policies\JadwalTahunanPolicy;
use Illuminate\Support\Facades\Validator;

class SearchJadwalOptions
{
    public function __construct(private readonly JadwalTahunanPolicy $policy) {}

    /** Pilihan minimal tidak membuka detail domain Renstra atau seluruh katalog. @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(User $actor, string $jenis, array $filters): array
    {
        $this->policy->viewAny($actor)->authorize();
        abort_unless(in_array($jenis, ['renstra', 'periode'], true), 404);
        $filters = Validator::make($filters, ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']])->validate();
        $query = $jenis === 'renstra'
            ? Renstra::query()->select(['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai'])->whereIn('status', ['draft', 'aktif'])->orderBy('tahun_mulai', 'desc')->orderBy('id')
            : Periode::query()->select(['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi'])->where('aktif', true)->orderBy('urutan')->orderBy('id');
        $rows = $query->when($filters['q'] ?? null, fn ($query, $q) => $query->where('nama', 'ilike', '%'.$q.'%'))
            ->simplePaginate(20, page: $filters['page'] ?? 1);

        $fields = $jenis === 'renstra' ? ['id', 'nama', 'status', 'tahun_mulai', 'tahun_selesai'] : ['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi'];

        return ['data' => $rows->getCollection()->map(fn ($row): array => $row->only($fields))->all(), 'has_more' => $rows->hasMorePages()];
    }
}
