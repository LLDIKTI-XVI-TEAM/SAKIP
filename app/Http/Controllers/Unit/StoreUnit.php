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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreUnit extends Controller
{
    public function __invoke(Request $request, AuditLogger $auditLogger, PermissionResolver $permissionResolver): RedirectResponse
    {
        Gate::authorize('create', Unit::class);

        $rawNama = $request->input('nama');
        if (is_string($rawNama)) {
            $request->merge(['nama' => trim($rawNama)]);
        }

        $validated = $request->validate([
            'nama' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $trimmed = trim((string) $value);
                    if ($trimmed === '') {
                        $fail('Nama unit organisasi tidak boleh kosong atau hanya berisi spasi.');

                        return;
                    }
                    if (Unit::whereRaw('LOWER(nama) = ?', [mb_strtolower($trimmed)])->exists()) {
                        $fail('Nama unit organisasi sudah digunakan.');
                    }
                },
            ],
            'status' => ['nullable', 'in:aktif,nonaktif'],
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
            $result = DB::transaction(function () use ($validated, $user, $auditLogger, $permissionResolver) {
                /** @var User $currentActor */
                $currentActor = User::with('roles')->whereKey($user->id)->sharedLock()->firstOrFail();

                // Evaluasi ulang wewenang aktor di dalam transaksi
                $currentDecision = $permissionResolver->resolve($currentActor, 'unit:create');
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
                    'nama' => trim($validated['nama']),
                    'status' => $validated['status'] ?? 'aktif',
                    'created_by' => $currentActor->id,
                ]);

                $auditLogger->catat(
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

        /** @var Unit $unit */
        $unit = $result['unit'];

        return redirect()->route('unit.index')->with('success', "Unit organisasi '{$unit->nama}' berhasil ditambahkan.");
    }
}
