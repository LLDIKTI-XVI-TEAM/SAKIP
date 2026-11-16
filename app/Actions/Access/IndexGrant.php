<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\Access\IzinUnitPresenter;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Support\Str;

class IndexGrant
{
    public function __construct(private readonly PermissionResolver $permissionResolver) {}

    /**
     * Daftar Izin Unit satu baris per pengguna × unit (Q62) beserta pilihan unit aktif dan permission yang boleh didelegasikan.
     * Paginasi menghitung kelompok; kelompok tampil bila pengguna, unit, atau salah satu izinnya cocok dengan pencarian.
     * Filter unit yang bukan UUID unit aktif jatuh kembali ke "all" agar query tidak membocorkan keberadaan unit nonaktif.
     *
     * `beri_pengguna`/`beri_unit` (tautan dari halaman Penanggung Jawab) menyiapkan isian Beri izin unit bila keduanya sah dan aktif.
     *
     * @return array<string, mixed>
     */
    public function handle(string $search, mixed $unitId, mixed $beriPengguna = null, mixed $beriUnit = null): array
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

        $berperanAktif = $this->permissionResolver->penggunaBerperanAktif($pasangan->pluck('user_id')->unique()->values()->all());

        // Pasangan yang dicabut di antara dua query dilewati; total paginasi boleh lebih satu sesaat.
        $kelompok->setCollection($pasangan->filter(fn (UserPermissionGrant $p) => $grants->has("{$p->user_id}:{$p->unit_id}"))
            ->values()->map(fn (UserPermissionGrant $p) => IzinUnitPresenter::kelompok($grants["{$p->user_id}:{$p->unit_id}"], $berperanAktif)));

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
            'beri' => $this->isianBeri($beriPengguna, $units->pluck('id')->contains($beriUnit) ? $beriUnit : null),
            'can' => [
                'create_grant' => true,
                'kelola_grant' => true,
                'revoke_grant' => true,
            ],
        ];
    }

    /**
     * Isian awal Beri izin unit; hanya pengguna aktif dan unit aktif yang ditawarkan, selain itu diabaikan tanpa galat.
     *
     * @return array{user: array{id: string, nama: string, email: string, status: string, roles: list<string>}, unit_id: string}|null
     */
    private function isianBeri(mixed $userId, mixed $unitId): ?array
    {
        if (! is_string($userId) || ! Str::isUuid($userId) || ! is_string($unitId)) {
            return null;
        }
        $user = User::with('roles:id,nama')->whereKey($userId)->where('status', 'aktif')->first(['id', 'nama', 'email', 'status']);

        return $user ? [
            'user' => ['id' => $user->id, 'nama' => $user->nama, 'email' => $user->email, 'status' => $user->status, 'roles' => $user->roles->pluck('nama')->values()->all()],
            'unit_id' => $unitId,
        ] : null;
    }
}
