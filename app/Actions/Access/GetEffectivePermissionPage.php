<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\RoleCatalog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type UnitSummary array{id: string, nama: string, status: string}
 * @phpstan-type StoredPermissionSource array{id: string, kind: 'role'|'grant'|'deny', label: string, unit: UnitSummary|null, alasan: string|null}
 * @phpstan-type PermissionRow array{
 *     id: string|null, kode: string, keterangan: string|null, scope: 'global'|'unit', aktif: bool,
 *     decision: array{allowed: bool, permission: string, reason: string, roles: list<string>, grants: list<string>, denies: list<string>},
 *     status: string, explanation: string,
 *     sources: list<array{id: string, kind: 'role'|'grant'|'deny', label: string, unit: UnitSummary|null, alasan: string|null, effective: bool}>
 * }
 * @phpstan-type PageLinks array{page: int, prev_page_url: string|null, next_page_url: string|null}
 */
class GetEffectivePermissionPage
{
    public function __construct(private PermissionResolver $resolver) {}

    /**
     * Menyiapkan halaman baca dengan keputusan resolver canonical; sumber tersimpan
     * tetap tampil untuk diagnosis tanpa memberikan izin baru.
     *
     * @param  array{user_id?: string|null, unit_id?: string|null, q?: string|null, scope?: ''|'global'|'unit'|null, page?: int|numeric-string|null, diagnostic_page?: int|numeric-string|null}  $input
     * @return array{
     *     selectedUser: array{id: string, nama: string, email: string, status: 'aktif'|'nonaktif', role: array{id: string, kode: string, nama: string, aktif: bool, in_catalog: bool}|null}|null,
     *     selectedUnit: UnitSummary|null, permissions: list<PermissionRow>, diagnostics: list<PermissionRow>,
     *     pagination: PageLinks, diagnosticPagination: PageLinks,
     *     filters: array{user_id: string|null, unit_id: string|null, q: string, scope: ''|'global'|'unit'}
     * }
     */
    public function execute(array $input): array
    {
        $filters = ['user_id' => $input['user_id'] ?? null, 'unit_id' => $input['unit_id'] ?? null,
            'q' => trim($input['q'] ?? ''), 'scope' => $input['scope'] ?? ''];
        $emptyPagination = ['page' => 1, 'prev_page_url' => null, 'next_page_url' => null];
        $props = ['selectedUser' => null, 'selectedUnit' => null, 'permissions' => [], 'diagnostics' => [],
            'pagination' => $emptyPagination, 'diagnosticPagination' => $emptyPagination, 'filters' => $filters];
        if ($filters['unit_id'] !== null) {
            $props['selectedUnit'] = Unit::findOrFail($filters['unit_id'], ['id', 'nama', 'status'])->only(['id', 'nama', 'status']);
        }
        if ($filters['user_id'] === null) {
            return $props;
        }
        $user = User::with('roles:id,kode,nama,aktif')->findOrFail($filters['user_id'], ['id', 'nama', 'email', 'status']);
        $role = $user->roles->first();
        $props['selectedUser'] = [...$user->only(['id', 'nama', 'email', 'status']), 'role' => $role === null ? null
            : [...$role->only(['id', 'kode', 'nama', 'aktif']), 'in_catalog' => RoleCatalog::contains($role->kode)]];

        // Katalog rilis terbatas; dataset yang tumbuh tetap difilter/dipaginasi di DB.
        $catalog = collect(PermissionCatalog::codes())->sort()->values();
        $metadata = Permission::whereIn('kode', $catalog)->get(['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'])->keyBy('kode');
        $catalog = $catalog->filter(function ($code) use ($metadata, $filters) {
            $permission = $metadata->get($code);
            $scope = $permission?->butuh_scope ?? (in_array($code, PermissionCatalog::UNIT_SCOPED, true) ? 'unit' : 'global');
            $description = $permission?->keterangan ?? PermissionCatalog::DEFAULT_DESCRIPTIONS[$code] ?? '';

            return ($filters['scope'] === '' || $filters['scope'] === $scope)
                && ($filters['q'] === '' || mb_stripos($code.' '.$description, $filters['q']) !== false);
        })->values();
        $pageNumber = (int) ($input['page'] ?? 1);
        $pageCodes = $catalog->slice(($pageNumber - 1) * 20, 20)->values()->all();
        $pagination = new LengthAwarePaginator($pageCodes, $catalog->count(), 20, $pageNumber, ['path' => route('effective-permission.index')]);
        $pagination->appends([...$filters, 'diagnostic_page' => $input['diagnostic_page'] ?? 1]);

        $legacy = Permission::whereNotIn('kode', PermissionCatalog::codes())
            ->where(function ($query) use ($user, $role, $filters) {
                if ($role !== null) {
                    $query->whereExists(fn ($source) => $source->selectRaw('1')->from('role_permissions')
                        ->whereColumn('role_permissions.permission_id', 'permissions.id')->where('role_id', $role->id));
                }
                foreach (['user_permission_granted', 'user_permission_denied'] as $table) {
                    $query->orWhereExists(fn ($source) => $source->selectRaw('1')->from($table)
                        ->whereColumn($table.'.permission_id', 'permissions.id')->where('user_id', $user->id)
                        ->where(function ($context) use ($filters) {
                            $context->whereNull('unit_id');
                            if ($filters['unit_id'] !== null) {
                                $context->orWhere('unit_id', $filters['unit_id']);
                            }
                        }));
                }
            })->when($filters['scope'] !== '', fn ($query) => $query->where('butuh_scope', $filters['scope']))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($search) => $search
                ->where('kode', 'ilike', '%'.$filters['q'].'%')->orWhere('keterangan', 'ilike', '%'.$filters['q'].'%')))
            ->orderBy('kode')->simplePaginate(20, ['id', 'kode', 'keterangan', 'butuh_scope', 'aktif'], 'diagnostic_page', (int) ($input['diagnostic_page'] ?? 1))
            ->withPath(route('effective-permission.index'))->appends([...$filters, 'page' => $pageNumber]);
        foreach ($legacy->items() as $permission) {
            $metadata->put($permission->kode, $permission);
        }
        $codes = [...$pageCodes, ...$legacy->getCollection()->pluck('kode')->all()];
        $decisions = $this->resolver->decideMany($user, $codes, $filters['unit_id']);
        $ids = $metadata->toBase()->only($codes)->pluck('id')->all();
        $sources = $this->sources($user, $role?->id, $ids, $filters['unit_id']);
        $rows = [];
        foreach ($codes as $code) {
            $permission = $metadata->get($code);
            $decision = $decisions[$code];
            [$status, $explanation] = $this->explain($decision['reason']);
            $rows[$code] = [
                'id' => $permission?->id, 'kode' => $code,
                'keterangan' => $permission?->keterangan ?? PermissionCatalog::DEFAULT_DESCRIPTIONS[$code] ?? null,
                'scope' => $permission?->butuh_scope ?? (in_array($code, PermissionCatalog::UNIT_SCOPED, true) ? 'unit' : 'global'),
                'aktif' => $permission?->aktif ?? false, 'decision' => $decision, 'status' => $status, 'explanation' => $explanation,
                'sources' => array_map(function ($source) use ($decision) {
                    $key = match ($source['kind']) {
                        'role' => 'roles', 'grant' => 'grants', 'deny' => 'denies'
                    };
                    $participates = in_array($source['id'], $decision[$key], true);

                    return [...$source, 'effective' => $participates && ($source['kind'] === 'deny' || $decision['allowed'])];
                }, $permission === null ? [] : ($sources[$permission->id] ?? [])),
            ];
        }
        $props['permissions'] = array_map(fn ($code) => $rows[$code], $pageCodes);
        $props['diagnostics'] = array_map(fn ($permission) => $rows[$permission->kode], $legacy->items());
        $props['pagination'] = ['page' => $pagination->currentPage(), 'prev_page_url' => $pagination->previousPageUrl(), 'next_page_url' => $pagination->nextPageUrl()];
        $props['diagnosticPagination'] = ['page' => $legacy->currentPage(), 'prev_page_url' => $legacy->previousPageUrl(), 'next_page_url' => $legacy->nextPageUrl()];

        return $props;
    }

    /**
     * Membatasi sumber pada permission halaman ini dan konteks Global/unit terpilih.
     * Efektivitas sumber ditentukan dari keputusan resolver setelah data ini dibaca.
     *
     * @param  list<string>  $ids
     * @return array<string, list<StoredPermissionSource>>
     */
    private function sources(User $user, ?string $roleId, array $ids, ?string $unitId): array
    {
        $sources = [];
        if ($roleId !== null) {
            $role = $user->roles->first();
            foreach (DB::table('role_permissions')->where('role_id', $roleId)->whereIn('permission_id', $ids)->pluck('permission_id') as $id) {
                $sources[$id][] = ['id' => $roleId, 'kind' => 'role', 'label' => 'Peran: '.$role->nama, 'unit' => null, 'alasan' => null];
            }
        }
        foreach (['grant' => 'user_permission_granted', 'deny' => 'user_permission_denied'] as $kind => $table) {
            $records = DB::table($table.' as source')->leftJoin('unit', 'unit.id', '=', 'source.unit_id')
                ->where('source.user_id', $user->id)->whereIn('source.permission_id', $ids)
                ->where(function ($query) use ($unitId) {
                    $query->whereNull('source.unit_id');
                    if ($unitId !== null) {
                        $query->orWhere('source.unit_id', $unitId);
                    }
                })->orderBy('source.id')->get(['source.id', 'source.permission_id', 'source.unit_id', 'source.alasan', 'unit.nama', 'unit.status']);
            foreach ($records as $record) {
                $sources[$record->permission_id][] = ['id' => $record->id, 'kind' => $kind, 'label' => $kind === 'grant' ? 'Grant' : 'Deny',
                    'unit' => $record->unit_id === null ? null : ['id' => $record->unit_id, 'nama' => $record->nama, 'status' => $record->status],
                    'alasan' => $record->alasan];
            }
        }

        return $sources;
    }

    /**
     * Label dan penjelasan mengikuti alasan keputusan resolver.
     *
     * @return array{string, string}
     */
    private function explain(string $reason): array
    {
        return match ($reason) {
            'allow' => ['Diizinkan', 'Ada sumber izin yang berlaku dan tidak ada Deny yang cocok.'],
            'explicit_deny' => ['Dicabut oleh Deny', 'Deny yang cocok mengalahkan semua sumber allow, termasuk peran Superadmin.'],
            'invalid_scope' => ['Pilih unit untuk memeriksa', 'Permission ini memerlukan konteks satu unit.'],
            'inactive_user' => ['Tidak efektif', 'Pengguna nonaktif; seluruh sumber izin tersimpan tidak efektif.'],
            'no_role' => ['Tidak efektif', 'Pengguna tidak memiliki peran resmi yang aktif; Grant tidak membuka akses.'],
            'unknown_permission' => ['Tidak efektif', 'Permission tidak aktif, tidak tersedia, atau berada di luar katalog rilis.'],
            'inactive_unit' => ['Tidak efektif', 'Grant pada unit nonaktif tidak memberikan izin.'],
            default => ['Tidak diberikan', 'Tidak ada sumber allow yang berlaku dalam konteks ini.'],
        };
    }
}
