<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class UpdateSasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ResolveLockedActor $lockedActor) {}

    /**
     * Memperbarui kode/deskripsi/urutan Sasaran. Keputusan izin dievaluasi
     * ulang di dalam transaksi terkunci memakai state terkini (anti-TOCTOU),
     * lalu baris dikunci dan nilai lama diambil dari baris terkunci agar
     * audit before/after sesuai state database sesaat sebelum mutasi
     * (bukan dari route binding).
     *
     * Penolakan dicatat sebagai audit `sasaran.ubah_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar.
     */
    public function handle(User $actor, SasaranStrategis $sasaran, array $validated): SasaranStrategis
    {
        $result = DB::transaction(function () use ($sasaran, $validated, $actor) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::SASARAN_UPDATE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembaruan sasaran strategis ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pembaruan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::query()
                ->whereKey($sasaran->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();

            $lockedSasaran->update([
                'kode' => trim($validated['kode']),
                'deskripsi' => trim($validated['deskripsi']),
                'urutan' => $validated['urutan'] ?? $lockedSasaran->urutan,
            ]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.ubah',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $lockedSasaran->withoutRelations()->toArray(),
                alasan: "Memperbarui sasaran strategis '{$lockedSasaran->kode}'.",
                dasarIzin: $dasarIzin,
            );

            return [
                'status' => 'updated',
                'sasaran' => $lockedSasaran,
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.ubah_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pembaruan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang mengubah sasaran strategis.');
        }

        return $result['sasaran'];
    }
}
