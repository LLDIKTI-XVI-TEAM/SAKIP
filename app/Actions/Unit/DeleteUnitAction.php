<?php

namespace App\Actions\Unit;

use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DeleteUnitAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    /**
     * Hapus Unit tanpa relasi setelah izin dan peran Superadmin diperiksa ulang di bawah lock.
     * Mutasi dan audit sukses atomik. Keputusan awal hanya menjadi basis audit FK setelah rollback;
     * izin mutasi selalu memakai keputusan terkini.
     */
    public function handle(User $actor, string $id, string $alasan, PermissionDecision $initialDecision): string
    {
        try {
            $result = DB::transaction(function () use ($id, $actor, $alasan) {
                /** @var User $currentActor */
                $currentActor = User::with('roles')->whereKey($actor->id)->sharedLock()->firstOrFail();

                // Urutan User → Role terurut menjaga izin tetap stabil selama mutasi.
                $currentActor->lockActiveRoles();

                // Otorisasi ulang aktor di dalam transaksi sebelum mutasi sensitif
                $currentDecision = $this->permissionResolver->resolve($currentActor, PermissionCodes::UNIT_DELETE);
                if (! $currentDecision->allowed || ! $currentActor->hasRole('superadmin')) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'unit.hapus_ditolak',
                        'objekTipe' => 'unit',
                        'objekId' => $id,
                        'nilaiLama' => null,
                        'alasan' => 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.',
                    ];
                }

                /** @var Unit $unit */
                $unit = Unit::whereKey($id)->lockForUpdate()->firstOrFail();

                // Evaluasi ulang relasi saat baris terkunci
                if (! $unit->isDeletable()) {
                    return [
                        'status' => 'denied',
                        'actor' => $currentActor,
                        'tindakan' => 'unit.hapus_ditolak',
                        'objekTipe' => 'unit',
                        'objekId' => (string) $unit->id,
                        'nilaiLama' => [
                            'id' => $unit->id,
                            'nama' => $unit->nama,
                            'status' => $unit->status,
                        ],
                        'alasan' => 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.',
                        'dasarIzin' => $currentDecision->toAuditBasis(),
                        'message' => 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.',
                    ];
                }

                $unitNama = $unit->nama;
                $oldValues = [
                    'id' => $unit->id,
                    'nama' => $unit->nama,
                    'status' => $unit->status,
                    'created_by' => $unit->created_by,
                ];

                $unit->delete();

                $this->auditLogger->catat(
                    actor: $currentActor,
                    tindakan: 'unit.hapus',
                    objekTipe: 'unit',
                    objekId: (string) $unit->id,
                    nilaiLama: $oldValues,
                    nilaiBaru: null,
                    alasan: $alasan,
                    dasarIzin: $currentDecision->toAuditBasis(),
                );

                return [
                    'status' => 'deleted',
                    'nama' => $unitNama,
                ];
            });
        } catch (QueryException $exception) {
            // FK diterjemahkan sesudah rollback; audit mempertahankan basis keputusan awal.
            if (($exception->errorInfo[0] ?? null) === '23503') {
                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'unit.hapus_ditolak',
                    objekTipe: 'unit',
                    objekId: $id,
                    nilaiLama: null,
                    nilaiBaru: null,
                    alasan: 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan relasi data.',
                    dasarIzin: $initialDecision->toAuditBasis(),
                );

                abort(403, 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, snapshot jadwal, atau izin terkait.');
            }

            throw $exception;
        }

        if ($result['status'] === 'denied') {
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

            abort(403, $result['message']);
        }

        return $result['nama'];
    }
}
