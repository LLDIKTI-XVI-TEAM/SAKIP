<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\UserPermissionGrant;
use App\Services\Access\IzinUnitPresenter;
use App\Services\Authorization\PermissionCatalog;
use Illuminate\Support\Str;

class IndexGrant
{
    /**
     * Daftar Izin Unit satu baris per pengguna × unit (Q60) beserta pilihan unit aktif dan permission yang boleh didelegasikan.
     * Paginasi menghitung kelompok; kelompok tampil bila pengguna, unit, atau salah satu izinnya cocok dengan pencarian.
     * Filter unit yang bukan UUID unit aktif jatuh kembali ke "all" agar query tidak membocorkan keberadaan unit nonaktif.
     *
     * @return array<string, mixed>
     */
    public function handle(string $search, mixed $unitId): array
    {
        $grantsQuery = UserPermissionGrant::query()
            ->whereNotNull('unit_id')
            ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT));

        if ($search !== '') {
            $kodeBerlabel = array_keys(array_filter(PermissionCatalog::LABEL_IZIN_UNIT, fn (string $label) => mb_stripos($label, $search) !== false));
            $grantsQuery->where(function ($q) use ($search, $kodeBerlabel) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                })
                    ->orWhereHas('permission', function ($pq) use ($search, $kodeBerlabel) {
                        $pq->where('kode', 'ilike', "%{$search}%")
                            ->orWhere('keterangan', 'ilike', "%{$search}%")
                            ->orWhereIn('kode', $kodeBerlabel);
                    })
                    ->orWhereHas('unit', function ($uq) use ($search) {
                        $uq->where('nama', 'ilike', "%{$search}%");
                    });
            });
        }

        if (! empty($unitId) && $unitId !== 'all') {
            if (is_string($unitId) && Str::isUuid($unitId) && Unit::where('id', $unitId)->where('status', 'aktif')->exists()) {
                $grantsQuery->where('unit_id', $unitId);
            } else {
                $unitId = 'all';
            }
        }

        $kelompok = $grantsQuery->select('user_id', 'unit_id')
            ->selectRaw('max(created_at) as terakhir')
            ->groupBy('user_id', 'unit_id')
            ->orderByDesc('terakhir')
            ->orderBy('user_id')
            ->orderBy('unit_id')
            ->paginate(15)
            ->withQueryString();

        // Seluruh izin kelompok di halaman ini dimuat sekali, termasuk izin yang tidak cocok dengan pencarian.
        $pasangan = $kelompok->getCollection();
        $grants = $pasangan->isEmpty() ? collect() : UserPermissionGrant::with(IzinUnitPresenter::RELASI)
            ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT))
            ->where(function ($q) use ($pasangan) {
                foreach ($pasangan as $p) {
                    $q->orWhere(fn ($w) => $w->where('user_id', $p->user_id)->where('unit_id', $p->unit_id));
                }
            })
            ->get()
            ->groupBy(fn (UserPermissionGrant $grant) => "{$grant->user_id}:{$grant->unit_id}");

        $kelompok->through(fn (UserPermissionGrant $p) => IzinUnitPresenter::kelompok($grants["{$p->user_id}:{$p->unit_id}"]));

        $units = Unit::where('status', 'aktif')
            ->select('id', 'nama')
            ->orderBy('nama')
            ->get();

        return [
            'grants' => $kelompok,
            'filters' => [
                'search' => $search,
                'unit_id' => $unitId ?? 'all',
            ],
            'units' => $units,
            'unitPermissions' => IzinUnitPresenter::pilihanIzin(),
            'can' => [
                'create_grant' => true,
                'kelola_grant' => true,
                'revoke_grant' => true,
            ],
        ];
    }
}
