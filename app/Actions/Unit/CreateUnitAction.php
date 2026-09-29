<?php

namespace App\Actions\Unit;

use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUnitAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Tambah Unit setelah izin aktor diperiksa ulang di bawah lock pengguna dan role.
     * Mutasi dan audit sukses atomik; penolakan izin diaudit sesudah transaksi.
     *
     * @param  array{nama:string,status?:'aktif'|'nonaktif'|null}  $data
     */
    public function handle(User $actor, array $data): Unit
    {
        try {
            $result = DB::transaction(function () use ($data, $actor) {
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
                $currentDecision = $this->permissionResolver->resolve($currentActor, PermissionCodes::UNIT_CREATE);
                if (! $currentDecision->allowed) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'unit.tambah_ditolak',
                        'objekTipe' => 'unit',
                        'objekId' => (string) Str::uuid(),
                        'alasan' => 'Anda tidak berwenang menambah unit organisasi.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Anda tidak berwenang menambah unit organisasi.',
                    ];
                }

                $unit = Unit::create([
                    'nama' => trim($data['nama']),
                    'status' => $data['status'] ?? 'aktif',
                    'created_by' => $currentActor->id,
                ]);

                $this->auditLogger->catat(
                    actor: $currentActor,
                    tindakan: 'unit.tambah',
                    objekTipe: 'unit',
                    objekId: (string) $unit->id,
                    nilaiLama: null,
                    nilaiBaru: [
                        'nama' => $unit->nama,
                        'status' => $unit->status,
                        'created_by' => $unit->created_by,
                    ],
                    alasan: "Penambahan unit organisasi '{$unit->nama}'.",
                    dasarIzin: $currentDecision->toAuditBasis(),
                );

                return [
                    'status' => 'created',
                    'unit' => $unit,
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

        return $result['unit'];
    }
}
