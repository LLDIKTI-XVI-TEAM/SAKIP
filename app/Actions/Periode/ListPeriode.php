<?php

namespace App\Actions\Periode;

use App\Models\Periode;
use App\Models\User;
use App\Policies\PeriodePolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ListPeriode
{
    public function __construct(private readonly PeriodePolicy $policy) {}

    /** Singleton final dan kandidat terpaginasinya berasal dari konfigurasi yang sama. @param array<string, mixed> $filters @return array<string, mixed> */
    public function handle(User $actor, array $filters): array
    {
        $this->policy->viewAny($actor)->authorize();
        $filters = Validator::make($filters, ['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])], 'sort' => ['nullable', Rule::in(['urutan', 'nama'])]])->validate();

        return DB::transaction(function () use ($actor, $filters): array {
            Periode::lockConfiguration();
            $rows = Periode::query()->select(['id', 'nama', 'urutan', 'aktif', 'is_nilai_akhir', 'revisi'])
                ->withExists(['jendela as metadata_locked' => fn ($query) => $query->whereHas('jadwal', fn ($query) => $query->whereIn('status', ['aktif', 'ditutup']))])
                ->when($filters['q'] ?? null, fn ($query, $q) => $query->where('nama', 'ilike', '%'.$q.'%'))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('aktif', $status === 'aktif'))
                ->orderBy($filters['sort'] ?? 'urutan')->orderBy('id')->paginate(20, page: $filters['page'] ?? 1)->withQueryString()
                ->through(fn (Periode $row): array => [...$row->masterAttributes(), 'metadata_locked' => (bool) $row->getAttribute('metadata_locked'),
                    'metadata_locked_reason' => $row->getAttribute('metadata_locked') ? 'Dirujuk jadwal aktif atau ditutup; hanya nama dan status aktif yang dapat dikoreksi.' : null]);
            $final = Periode::where('aktif', true)->where('is_nilai_akhir', true)->first(['id', 'nama', 'revisi']);
            $canUpdate = $this->policy->update($actor)->allowed();

            return ['periode' => $rows, 'current_final' => $final?->only(['id', 'nama', 'revisi']),
                'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '', 'sort' => $filters['sort'] ?? 'urutan'],
                'can' => ['create' => $this->policy->create($actor)->allowed(), 'update' => $canUpdate, 'replaceFinal' => $canUpdate]];
        });
    }
}
