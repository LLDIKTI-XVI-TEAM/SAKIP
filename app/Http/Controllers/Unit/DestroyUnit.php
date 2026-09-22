<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DestroyUnit extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        if (! $actor) {
            abort(401);
        }

        $decision = $permissionResolver->resolve($actor, 'unit:delete');
        if (! $decision->allowed || ! $actor->hasRole('superadmin')) {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'unit.hapus_ditolak',
                objekTipe: 'unit',
                objekId: $id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.',
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, 'Hanya peran Superadmin yang berwenang menghapus unit organisasi.');
        }

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'alasan.required' => 'Alasan penghapusan unit wajib diisi sebagai dasar audit.',
            'alasan.min' => 'Alasan penghapusan unit minimal 5 karakter.',
        ]);
        $alasan = trim($validated['alasan']);

        try {
            $result = DB::transaction(function () use ($id, $actor, $alasan, $auditLogger, $decision) {
                /** @var Unit $unit */
                $unit = Unit::whereKey($id)->lockForUpdate()->firstOrFail();

                // Evaluasi ulang relasi saat baris terkunci
                if (! $unit->isDeletable()) {
                    return [
                        'status' => 'denied',
                        'unit' => $unit,
                        'message' => 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, atau izin terkait.',
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

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'unit.hapus',
                    objekTipe: 'unit',
                    objekId: (string) $unit->id,
                    nilaiLama: $oldValues,
                    nilaiBaru: null,
                    alasan: $alasan,
                    dasarIzin: $decision->toAuditBasis(),
                );

                return [
                    'status' => 'deleted',
                    'nama' => $unitNama,
                ];
            });
        } catch (QueryException $exception) {
            // SQLSTATE 23503: foreign_key_violation in PostgreSQL
            if (($exception->errorInfo[0] ?? null) === '23503') {
                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'unit.hapus_ditolak',
                    objekTipe: 'unit',
                    objekId: $id,
                    nilaiLama: null,
                    nilaiBaru: null,
                    alasan: 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan relasi data.',
                    dasarIzin: $decision->toAuditBasis(),
                );

                abort(403, 'Unit organisasi tidak dapat dihapus karena masih memiliki keterkaitan dengan indikator kinerja, rencana aksi, kegiatan, atau izin terkait.');
            }

            throw $exception;
        }

        if ($result['status'] === 'denied') {
            $auditLogger->catat(
                actor: $actor,
                tindakan: 'unit.hapus_ditolak',
                objekTipe: 'unit',
                objekId: (string) $result['unit']->id,
                nilaiLama: [
                    'id' => $result['unit']->id,
                    'nama' => $result['unit']->nama,
                    'status' => $result['unit']->status,
                ],
                nilaiBaru: null,
                alasan: $result['message'],
                dasarIzin: $decision->toAuditBasis(),
            );

            abort(403, $result['message']);
        }

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$result['nama']}' berhasil dihapus.");
    }
}
