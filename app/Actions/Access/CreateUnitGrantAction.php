<?php

namespace App\Actions\Access;

use App\Models\Permission;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUnitGrantAction
{
    /** Alasan baku server bila pemberi izin mengosongkan alasan (Q43 §43.3 butir 1). */
    public const ALASAN_BAKU = 'Pemberian izin unit tanpa alasan tambahan.';

    public const IZIN_TIDAK_SAH = 'Izin tidak tersedia, sudah dinonaktifkan, atau tidak dapat diberikan sebagai izin unit.';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Beri satu atau beberapa Grant Unit kepada satu pengguna pada satu unit setelah izin aktor serta target, unit,
     * dan permission diverifikasi ulang. Semua atau tidak sama sekali: pengguna terurut dikunci sebelum role, unit,
     * dan permission; setiap grant beserta auditnya atomik dalam satu transaksi.
     *
     * @param  array{user_id:string,permission_ids:list<string>,unit_id?:string|null,alasan?:string|null}  $data
     * @return array{jumlah:int,unit_nama:string,target_nama:string}
     */
    public function handle(User $actor, array $data): array
    {
        /** @var User|null $targetUser */
        $targetUser = User::with('roles')->find($data['user_id']);
        if (! $targetUser || $targetUser->status !== 'aktif') {
            throw ValidationException::withMessages([
                'user_id' => 'Pengguna tidak ditemukan atau akunnya nonaktif.',
            ]);
        }

        $permissionIds = array_values(array_unique($data['permission_ids']));
        // Urutan id tetap menjadi urutan lock permission agar dua pemberian bersamaan tidak saling menunggu.
        sort($permissionIds);
        $this->pastikanIzinSah(Permission::whereKey($permissionIds)->get(), $permissionIds);

        // Permission unit wajib menyertakan unit target.
        if (empty($data['unit_id'])) {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit wajib dipilih.',
            ]);
        }

        // Pastikan unit_id valid dan aktif
        /** @var Unit|null $unit */
        $unit = Unit::find($data['unit_id']);
        if (! $unit || $unit->status !== 'aktif') {
            throw ValidationException::withMessages([
                'unit_id' => 'Unit tidak ditemukan atau berstatus nonaktif.',
            ]);
        }

        // Pemeriksaan awal duplikasi; indeks unik tetap menjadi penjaga terakhir.
        $sudahAda = UserPermissionGrant::where('user_id', $targetUser->id)
            ->where('unit_id', $unit->id)
            ->whereIn('permission_id', $permissionIds)
            ->with('permission:id,kode')
            ->get();

        if ($sudahAda->isNotEmpty()) {
            $label = $this->urutKatalog($sudahAda->pluck('permission'))->map(fn (Permission $p) => PermissionCatalog::LABEL_IZIN_UNIT[$p->kode])->implode(', ');
            throw ValidationException::withMessages([
                'permission_ids' => "Pengguna sudah memiliki izin ini pada unit tersebut: {$label}.",
            ]);
        }

        try {
            $result = DB::transaction(function () use ($targetUser, $permissionIds, $unit, $data, $actor) {
                // Kunci pengguna dengan urutan ID konsisten untuk menghindari deadlock
                $userIds = [$actor->id, $targetUser->id];
                sort($userIds);
                $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

                /** @var User $currentActor */
                $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

                /** @var User $lockedTargetUser */
                $lockedTargetUser = $lockedUsers->get($targetUser->id) ?? User::with('roles')->whereKey($targetUser->id)->sharedLock()->firstOrFail();

                // Kunci role sumber terurut agar perubahan preset tidak menyela evaluasi izin.
                $currentActor->lockActiveRoles();

                // Otorisasi ulang aktor di dalam transaksi untuk mencegah race condition pencabutan hak akses
                $currentDecision = $this->permissionResolver->resolve($currentActor, PermissionCodes::DELEGASI_UPDATE);
                if (! $currentDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'user_permission_granted.ditolak',
                        'objekTipe' => 'user_permission_granted',
                        'objekId' => (string) Str::uuid(),
                        'alasan' => 'Anda tidak berwenang mengelola pemberian izin unit.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Anda tidak berwenang mengelola pemberian izin unit.',
                    ];
                }

                // Validasi ulang status aktif pengguna target di dalam transaksi untuk mencegah TOCTOU
                if ($lockedTargetUser->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'user_id' => 'Pengguna tidak ditemukan atau akunnya nonaktif.',
                    ]);
                }

                // Kunci unit dengan sharedLock agar urutan penguncian konsisten terhadap lockForUpdate di penghapusan unit,
                // dan validasi ulang status aktif unit di dalam transaksi untuk mencegah TOCTOU
                /** @var Unit $lockedUnit */
                $lockedUnit = Unit::whereKey($unit->id)->sharedLock()->firstOrFail();
                if ($lockedUnit->status !== 'aktif') {
                    throw ValidationException::withMessages([
                        'unit_id' => 'Unit tidak ditemukan atau berstatus nonaktif.',
                    ]);
                }

                // Kunci permission dengan sharedLock dan validasi ulang status aktif serta butuh_scope di dalam transaksi
                $lockedPermissions = Permission::whereKey($permissionIds)->orderBy('id')->sharedLock()->get();
                $this->pastikanIzinSah($lockedPermissions, $permissionIds);

                $alasan = AlasanAudit::sanitasi($data['alasan'] ?? null, self::ALASAN_BAKU);
                foreach ($this->urutKatalog($lockedPermissions) as $lockedPermission) {
                    $grant = UserPermissionGrant::create([
                        'user_id' => $lockedTargetUser->id,
                        'permission_id' => $lockedPermission->id,
                        'unit_id' => $lockedUnit->id,
                        'alasan' => $alasan,
                        'diberikan_oleh' => $currentActor->id,
                    ]);

                    $this->auditLogger->catat(
                        actor: $currentActor,
                        tindakan: 'user_permission_granted.tambah',
                        objekTipe: 'user_permission_granted',
                        objekId: (string) $grant->id,
                        nilaiLama: null,
                        nilaiBaru: self::nilaiBaruAudit($grant, $lockedTargetUser, $lockedPermission, $lockedUnit),
                        alasan: $grant->alasan,
                        dasarIzin: $currentDecision->toAuditBasis(),
                    );
                }

                return [
                    'status' => 'created',
                    'jumlah' => $lockedPermissions->count(),
                    'unit_nama' => $lockedUnit->nama,
                    'target_nama' => $lockedTargetUser->nama,
                ];
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'permission_ids' => self::IZIN_TIDAK_SAH,
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->errorInfo[2] ?? '', 'user_permission_granted')) {
                throw ValidationException::withMessages([
                    'permission_ids' => 'Pengguna sudah memiliki salah satu izin ini pada unit tersebut.',
                ]);
            }
            throw $exception;
        }

        if (is_array($result) && ($result['status'] ?? null) === 'denied') {
            $this->auditLogger->catat(
                actor: $result['actor'],
                tindakan: $result['tindakan'],
                objekTipe: $result['objekTipe'],
                objekId: $result['objekId'],
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'],
                dasarIzin: $result['dasarIzin'],
            );

            abort(403, $result['message']);
        }

        return [
            'jumlah' => $result['jumlah'],
            'unit_nama' => $result['unit_nama'],
            'target_nama' => $result['target_nama'],
        ];
    }

    /**
     * Grant Unit tidak memberikan permission global: setiap izin harus ada, aktif, unit-scoped, dan termasuk 7 izin katalog.
     *
     * @param  Collection<int, Permission>  $permissions
     * @param  list<string>  $permissionIds
     */
    private function pastikanIzinSah(Collection $permissions, array $permissionIds): void
    {
        if ($permissions->count() !== count($permissionIds) || $permissions->contains(fn (Permission $p) => ! self::dapatDiberikan($p))) {
            throw ValidationException::withMessages([
                'permission_ids' => self::IZIN_TIDAK_SAH,
            ]);
        }
    }

    /**
     * Isi `nilai_baru` audit pemberian; dipakai juga Kelola izin agar peristiwa yang sama tercatat dengan bentuk sama.
     *
     * @return array<string, mixed>
     */
    public static function nilaiBaruAudit(UserPermissionGrant $grant, User $target, Permission $permission, Unit $unit): array
    {
        return [
            'user_id' => $target->id,
            'user_nama' => $target->nama,
            'permission_id' => $permission->id,
            'permission_kode' => $permission->kode,
            'unit_id' => $unit->id,
            'unit_nama' => $unit->nama,
            'alasan' => $grant->alasan,
            'diberikan_oleh' => $grant->diberikan_oleh,
        ];
    }

    /** Izin yang boleh diberikan baru: aktif, unit-scoped, dan termasuk 7 izin katalog. */
    public static function dapatDiberikan(Permission $permission): bool
    {
        return $permission->aktif
            && $permission->butuh_scope === Permission::SCOPE_UNIT
            && in_array($permission->kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true);
    }

    /**
     * @param  BaseCollection<int, Permission>  $permissions
     * @return BaseCollection<int, Permission>
     */
    private function urutKatalog(BaseCollection $permissions): BaseCollection
    {
        return $permissions->sortBy(fn (Permission $p) => array_search($p->kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true))->values();
    }
}
