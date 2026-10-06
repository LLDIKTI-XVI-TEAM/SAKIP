<?php

namespace App\Actions\Jadwal;

use App\Http\Requests\Jadwal\StoreJadwalRequest;
use App\Models\JadwalTahunan;
use App\Models\User;
use App\Policies\JadwalTahunanPolicy;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ListJadwal
{
    public function __construct(private readonly JadwalTahunanPolicy $policy) {}

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(User $actor, array $filters): array
    {
        $this->policy->viewAny($actor)->authorize();
        $request = new StoreJadwalRequest;
        $filters = Validator::make($filters, ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'aktif', 'ditutup'])], 'sort' => ['nullable', Rule::in(['tahun_desc', 'tahun_asc'])],
            'renstra_id' => ['nullable', 'uuid'], 'tahun' => ['nullable', 'integer', 'between:1,9998']], $request->messages(), $request->attributes())->validate();
        $canUpdate = $this->policy->update($actor)->allowed();
        $rows = JadwalTahunan::query()->select(['id', 'renstra_id', 'tahun', 'status', 'revisi', 'penutupan', 'activated_at'])
            ->with('renstra:id,nama,status')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->whereHas('renstra', fn ($query) => $query->where('nama', 'ilike', '%'.$q.'%')))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['renstra_id'] ?? null, fn ($query, $value) => $query->where('renstra_id', $value))
            ->when($filters['tahun'] ?? null, fn ($query, $value) => $query->where('tahun', $value))
            ->orderBy('tahun', ($filters['sort'] ?? 'tahun_desc') === 'tahun_asc' ? 'asc' : 'desc')->orderBy('id')
            ->paginate(20, page: $filters['page'] ?? 1)->withQueryString()->through(fn (JadwalTahunan $row): array => [
                ...$row->only(['id', 'tahun', 'status', 'revisi']), 'renstra' => $row->renstra->only(['id', 'nama', 'status']),
                'penutupan' => $row->penutupan?->format('Y-m-d'),
                'can_update' => $canUpdate && $row->status === 'draft' && ! $row->is_terkunci && in_array($row->renstra->status, ['draft', 'aktif'], true),
            ]);

        return ['jadwal' => $rows, 'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '',
            'sort' => $filters['sort'] ?? 'tahun_desc', 'renstra_id' => $filters['renstra_id'] ?? '', 'tahun' => isset($filters['tahun']) ? (string) $filters['tahun'] : ''],
            'can' => ['create' => $this->policy->create($actor)->allowed()]];
    }
}
