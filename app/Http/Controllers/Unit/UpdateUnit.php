<?php

namespace App\Http\Controllers\Unit;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserPermissionGrant;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateUnit extends Controller
{
    public function __invoke(Request $request, string $id, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        /** @var Unit $unit */
        $unit = Unit::findOrFail($id);
        Gate::authorize('update', $unit);

        $rawNama = $request->input('nama');
        if (is_string($rawNama)) {
            $request->merge(['nama' => trim($rawNama)]);
        }

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($unit): void {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        $fail('Nama unit organisasi tidak boleh kosong atau hanya berisi spasi.');

                        return;
                    }
                    if (Unit::where('id', '!=', $unit->id)->whereRaw('LOWER(nama) = ?', [mb_strtolower($trimmed)])->exists()) {
                        $fail('Nama unit organisasi sudah digunakan.');
                    }
                },
            ],
            'status' => [
                'required',
                'in:aktif,nonaktif',
                function (string $attribute, mixed $value, \Closure $fail) use ($unit): void {
                    if ($value === 'nonaktif' && UserPermissionGrant::where('unit_id', $unit->id)->exists()) {
                        $fail('Unit tidak dapat dinonaktifkan karena masih memiliki grant izin aktif. Cabut semua grant unit terlebih dahulu.');
                    }
                },
            ],
        ], [
            'nama.required' => 'Nama unit organisasi wajib diisi.',
            'nama.max' => 'Nama unit organisasi maksimal 255 karakter.',
        ]);

        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        try {
            $result = DB::transaction(function () use ($id, $validated, $user, $auditLogger, $permissionResolver) {
                /** @var User $currentActor */
                $currentActor = User::with('roles')->whereKey($user->id)->sharedLock()->firstOrFail();

                // Evaluasi ulang wewenang aktor di dalam transaksi
                $currentDecision = $permissionResolver->resolve($currentActor, 'unit:update');
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

                // Cegah penonaktifan unit jika masih memiliki grant izin aktif (anti-TOCTOU)
                if ($validated['status'] === 'nonaktif' && UserPermissionGrant::where('unit_id', $lockedUnit->id)->exists()) {
                    throw ValidationException::withMessages([
                        'status' => 'Unit tidak dapat dinonaktifkan karena masih memiliki grant izin aktif. Cabut semua grant unit terlebih dahulu.',
                    ]);
                }

                $oldValues = [
                    'nama' => $lockedUnit->nama,
                    'status' => $lockedUnit->status,
                ];

                $lockedUnit->update([
                    'nama' => trim($validated['nama']),
                    'status' => $validated['status'],
                ]);

                $auditLogger->catat(
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

        if (is_array($result) && ($result['status'] ?? null) === 'denied') {
            $auditLogger->catat(
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

        $nama = $result['nama'];

        return redirect()->route('unit.index')->with('success', "Data unit '{$nama}' berhasil diperbarui.");
    }
}
