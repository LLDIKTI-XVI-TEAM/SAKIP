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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KelolaIzinUnitAction
{
    private const DATA_BERUBAH = 'Izin unit pengguna ini telah berubah. Muat ulang data sebelum menyimpan.';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Samakan Izin Unit satu pengguna pada satu unit dengan daftar akhir (Q61): izin baru diberikan, izin yang dilepas dicabut.
     * Semua atau tidak sama sekali; daftar yang dilihat (`expected_permission_ids`) harus sama dengan data tersimpan.
     * Urutan lock: pengguna terurut → peran aktor → unit → permission → grant pasangan itu; audit tetap per izin.
     *
     * @param  array{user_id:string,unit_id:string,permission_ids:list<string>,expected_permission_ids:list<string>,alasan?:string|null}  $data
     * @return array{ditambah:int,dicabut:int,sisa:int,target_nama:string,unit_nama:string}
     */
    public function handle(User $actor, array $data): array
    {
        $akhir = $this->urutUnik($data['permission_ids']);
        $dilihat = $this->urutUnik($data['expected_permission_ids']);
        $alasan = trim((string) ($data['alasan'] ?? ''));

        try {
            $result = DB::transaction(function () use ($actor, $data, $akhir, $dilihat, $alasan) {
                $userIds = array_values(array_unique([$actor->id, $data['user_id']]));
                sort($userIds);
                $lockedUsers = User::with('roles')->whereIn('id', $userIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

                /** @var User $currentActor */
                $currentActor = $lockedUsers->get($actor->id) ?? User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();
                /** @var User $target */
                $target = $lockedUsers->get($data['user_id']) ?? User::whereKey($data['user_id'])->sharedLock()->firstOrFail();

                // Kunci role sumber terurut agar perubahan preset tidak menyela evaluasi izin.
                $currentActor->lockActiveRoles();

                // Otorisasi ulang aktor di dalam transaksi untuk mencegah race condition pencabutan hak akses
                $decision = $this->permissionResolver->resolve($currentActor, PermissionCodes::DELEGASI_UPDATE);
                if (! $decision->allowed) {
                    return ['status' => 'denied', 'actor' => $currentActor, 'dasarIzin' => $decision->toAuditBasis()];
                }

                /** @var Unit $unit */
                $unit = Unit::whereKey($data['unit_id'])->sharedLock()->firstOrFail();
                $permissions = Permission::whereKey($this->urutUnik([...$akhir, ...$dilihat]))->orderBy('id')->sharedLock()->get()->keyBy('id');
                $grants = UserPermissionGrant::with('permission')
                    ->where('user_id', $target->id)
                    ->where('unit_id', $unit->id)
                    ->whereHas('permission', fn ($q) => $q->where('butuh_scope', Permission::SCOPE_UNIT))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($this->urutUnik($grants->pluck('permission_id')->all()) !== $dilihat) {
                    throw ValidationException::withMessages(['expected_permission_ids' => self::DATA_BERUBAH]);
                }

                $tambah = array_values(array_diff($akhir, $dilihat));
                $cabut = $grants->reject(fn (UserPermissionGrant $grant) => in_array($grant->permission_id, $akhir, true))->values();

                if ($tambah === [] && $cabut->isEmpty()) {
                    throw ValidationException::withMessages(['permission_ids' => 'Tidak ada perubahan izin.']);
                }
                // Pencabutan tetap wajib beralasan (Q43 §43.2 butir 4); menambah saja boleh tanpa alasan (Q43 §43.3 butir 1).
                if ($cabut->isNotEmpty() && $alasan === '') {
                    throw ValidationException::withMessages(['alasan' => 'Alasan wajib diisi bila ada izin yang dicabut.']);
                }
                if ($tambah !== []) {
                    $this->pastikanBolehMenambah($target, $unit, collect($tambah)->map(fn (string $id) => $permissions->get($id))->all());
                }

                $dasarIzin = $decision->toAuditBasis();
                $alasanTambah = AlasanAudit::sanitasi($alasan, CreateUnitGrantAction::ALASAN_BAKU);
                $urutKatalog = fn (?string $kode) => array_search($kode, PermissionCatalog::GRANTABLE_UNIT_PERMISSIONS, true);

                foreach (collect($tambah)->map(fn (string $id) => $permissions->get($id))->sortBy(fn (Permission $p) => $urutKatalog($p->kode)) as $permission) {
                    $grant = UserPermissionGrant::create([
                        'user_id' => $target->id,
                        'permission_id' => $permission->id,
                        'unit_id' => $unit->id,
                        'alasan' => $alasanTambah,
                        'diberikan_oleh' => $currentActor->id,
                    ]);
                    $this->auditLogger->catat(
                        actor: $currentActor,
                        tindakan: 'user_permission_granted.tambah',
                        objekTipe: 'user_permission_granted',
                        objekId: (string) $grant->id,
                        nilaiLama: null,
                        nilaiBaru: CreateUnitGrantAction::nilaiBaruAudit($grant, $target, $permission, $unit),
                        alasan: $grant->alasan,
                        dasarIzin: $dasarIzin,
                    );
                }

                foreach ($cabut->sortBy(fn (UserPermissionGrant $grant) => $urutKatalog($grant->permission?->kode)) as $grant) {
                    $nilaiLama = RevokeUnitGrantAction::nilaiLamaAudit($grant, $target->nama, $unit->nama);
                    $grantId = (string) $grant->id;
                    $grant->delete();
                    $this->auditLogger->catat(
                        actor: $currentActor,
                        tindakan: 'user_permission_granted.hapus',
                        objekTipe: 'user_permission_granted',
                        objekId: $grantId,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: null,
                        alasan: $alasan,
                        dasarIzin: $dasarIzin,
                    );
                }

                return [
                    'status' => 'ok',
                    'ditambah' => count($tambah),
                    'dicabut' => $cabut->count(),
                    'sisa' => $grants->count() - $cabut->count() + count($tambah),
                    'target_nama' => $target->nama,
                    'unit_nama' => $unit->nama,
                ];
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505'
                && str_contains($exception->errorInfo[2] ?? '', 'user_permission_granted')) {
                throw ValidationException::withMessages(['expected_permission_ids' => self::DATA_BERUBAH]);
            }
            throw $exception;
        }

        if ($result['status'] === 'denied') {
            // Penolakan diaudit di luar transaksi agar tetap tercatat walau transaksi dibatalkan.
            $this->auditLogger->catat(
                actor: $result['actor'],
                tindakan: 'user_permission_granted.ditolak',
                objekTipe: 'user_permission_granted',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Anda tidak berwenang mengelola izin unit.',
                dasarIzin: $result['dasarIzin'],
            );

            abort(403, 'Anda tidak berwenang mengelola izin unit.');
        }

        return [
            'ditambah' => $result['ditambah'],
            'dicabut' => $result['dicabut'],
            'sisa' => $result['sisa'],
            'target_nama' => $result['target_nama'],
            'unit_nama' => $result['unit_nama'],
        ];
    }

    /**
     * Izin baru hanya untuk akun dan unit aktif serta izin katalog yang aktif; pencabutan tidak dibatasi status.
     *
     * @param  list<Permission|null>  $permissions
     */
    private function pastikanBolehMenambah(User $target, Unit $unit, array $permissions): void
    {
        if ($target->status !== 'aktif') {
            throw ValidationException::withMessages(['user_id' => 'Akun pengguna nonaktif; izin baru tidak dapat ditambahkan.']);
        }
        if ($unit->status !== 'aktif') {
            throw ValidationException::withMessages(['unit_id' => 'Unit nonaktif; izin baru tidak dapat ditambahkan.']);
        }
        foreach ($permissions as $permission) {
            if (! $permission || ! CreateUnitGrantAction::dapatDiberikan($permission)) {
                throw ValidationException::withMessages(['permission_ids' => CreateUnitGrantAction::IZIN_TIDAK_SAH]);
            }
        }
    }

    /**
     * @param  array<int, string>  $ids
     * @return list<string>
     */
    private function urutUnik(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return $ids;
    }
}
