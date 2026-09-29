<?php

namespace App\Actions\Unit;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUnitAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Perbarui Unit hanya bila izin aktor dan snapshot terhadap Unit terkunci masih sah.
     * Mutasi dan audit sukses atomik; penolakan izin/stale diaudit sesudah transaksi.
     *
     * @param  array{nama:string,status:'aktif'|'nonaktif',version_token:?string,expected_nama:?string,expected_status:?string,snapshot:?array{nama?:mixed,status?:mixed}}  $data
     */
    public function handle(User $actor, string $id, array $data): string
    {
        try {
            $result = DB::transaction(function () use ($id, $data, $actor) {
                /** @var User $currentActor */
                $currentActor = User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

                // Urutan User → Role terurut menjaga izin tetap stabil selama mutasi.
                $actorRoleIds = DB::table('user_roles')
                    ->join('roles', 'roles.id', '=', 'user_roles.role_id')
                    ->where('user_roles.user_id', $currentActor->id)
                    ->where('roles.aktif', true)
                    ->pluck('roles.id')
                    ->all();
                sort($actorRoleIds);
                if (! empty($actorRoleIds)) {
                    Role::whereIn('id', $actorRoleIds)->orderBy('id')->sharedLock()->get();
                }

                // Evaluasi ulang wewenang aktor di dalam transaksi
                $currentDecision = $this->permissionResolver->resolve($currentActor, PermissionCodes::UNIT_UPDATE);
                if (! $currentDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'unit.ubah_ditolak',
                        'objekTipe' => 'unit',
                        'objekId' => $id,
                        'nilaiLama' => null,
                        'alasan' => 'Anda tidak berwenang mengubah unit organisasi.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Anda tidak berwenang mengubah unit organisasi.',
                    ];
                }

                /** @var Unit $lockedUnit */
                $lockedUnit = Unit::whereKey($id)->lockForUpdate()->firstOrFail();

                // Bandingkan snapshot form dengan data yang sudah terkunci.
                if ($lockedUnit->isSnapshotStale(
                    $data['version_token'],
                    $data['expected_nama'],
                    $data['expected_status'],
                    $data['snapshot'],
                )) {
                    return [
                        'status' => 'stale',
                        'actor' => $currentActor,
                        'tindakan' => 'unit.ubah_ditolak',
                        'objekTipe' => 'unit',
                        'objekId' => (string) $lockedUnit->id,
                        'nilaiLama' => [
                            'id' => $lockedUnit->id,
                            'nama' => $lockedUnit->nama,
                            'status' => $lockedUnit->status,
                        ],
                        'alasan' => 'Data unit organisasi telah diubah oleh pengguna lain. Muat ulang data terbaru sebelum menyimpan perubahan.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                    ];
                }

                // Cegah penonaktifan unit jika masih memiliki grant izin aktif (anti-TOCTOU)
                if ($data['status'] === 'nonaktif' && UserPermissionGrant::where('unit_id', $lockedUnit->id)->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Unit tidak dapat dinonaktifkan karena masih memiliki grant izin aktif. Cabut semua grant unit terlebih dahulu.',
                    ]);
                }

                $oldValues = [
                    'nama' => $lockedUnit->nama,
                    'status' => $lockedUnit->status,
                ];

                $lockedUnit->update([
                    'nama' => trim($data['nama']),
                    'status' => $data['status'],
                ]);

                $this->auditLogger->catat(
                    actor: $currentActor,
                    tindakan: 'unit.ubah',
                    objekTipe: 'unit',
                    objekId: (string) $lockedUnit->id,
                    nilaiLama: $oldValues,
                    nilaiBaru: [
                        'nama' => $lockedUnit->nama,
                        'status' => $lockedUnit->status,
                    ],
                    alasan: "Perubahan data unit organisasi '{$lockedUnit->nama}'.",
                    dasarIzin: $currentDecision->toAuditBasis(),
                );

                return [
                    'status' => 'updated',
                    'nama' => $lockedUnit->nama,
                ];
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'nama' => 'Nama unit organisasi sudah digunakan.',
                ]);
            }

            throw $exception;
        }

        if (in_array($result['status'], ['denied', 'stale'], true)) {
            $this->auditLogger->catat(
                actor: $result['actor'],
                tindakan: $result['tindakan'],
                objekTipe: $result['objekTipe'],
                objekId: $result['objekId'],
                nilaiLama: $result['nilaiLama'],
                nilaiBaru: null,
                alasan: $result['alasan'],
                dasarIzin: $result['dasarIzin'],
            );

            if ($result['status'] === 'denied') {
                abort(403, $result['message']);
            }

            throw ValidationException::withMessages([
                'status' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
                'nama' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
                'version_token' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
                'snapshot' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
                'expected_state' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
                'konflik' => 'Data unit organisasi telah diubah oleh pengguna lain. Silakan muat ulang halaman untuk mendapatkan data terbaru.',
            ]);
        }

        return $result['nama'];
    }
}
