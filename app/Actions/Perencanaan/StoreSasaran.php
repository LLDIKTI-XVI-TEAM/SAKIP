<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreSasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ResolveLockedActor $lockedActor) {}

    /**
     * Membuat satu Sasaran di bawah Renstra terpilih beserta audit pembuatannya.
     *
     * Keputusan izin dievaluasi ulang di dalam transaksi terkunci memakai
     * state terkini (anti-TOCTOU): pencabutan peran/grant/deny atau
     * penonaktifan akun di tengah jalan membuat operasi gagal tertutup.
     *
     * Penolakan dicatat sebagai audit `sasaran.buat_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar.
     */
    public function handle(User $actor, array $validated): SasaranStrategis
    {
        $result = DB::transaction(function () use ($validated, $actor) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::SASARAN_CREATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan sasaran strategis ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembuatan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            $created = SasaranStrategis::create([
                'renstra_id' => $validated['renstra_id'],
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? 0,
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat',
                objekTipe: 'sasaran',
                objekId: (string) $created->id,
                nilaiLama: null,
                nilaiBaru: $created->toArray(),
                alasan: "Menambah sasaran strategis '{$created->kode}'.",
                dasarIzin: $dasarIzin,
            );

            return [
                'status' => 'created',
                'sasaran' => $created,
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.buat_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) Str::uuid(),
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pembuatan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang menambah sasaran strategis.');
        }

        return $result['sasaran'];
    }
}
