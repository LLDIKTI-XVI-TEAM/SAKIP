<?php

namespace App\Actions\Perencanaan;

use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DestroySasaran
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ResolveLockedActor $lockedActor) {}

    /**
     * Menghapus Sasaran yang belum memiliki Indikator. Keputusan izin
     * dievaluasi ulang di dalam transaksi terkunci memakai state terkini
     * (anti-TOCTOU) sebelum baris sasaran dikunci; pemeriksaan anak dan
     * penghapusan berjalan dalam satu transaksi terkunci agar insert anak
     * konkuren tidak tersapu cascade-delete.
     *
     * Penolakan izin dicatat sebagai audit `sasaran.hapus_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar. Bila masih
     * beranak, kegagalan guard dicatat sebagai audit penolakan (tanpa
     * mengubah data) lalu ValidationException dilempar seperti validasi
     * form biasa.
     *
     * @return array{kode: string}
     */
    public function handle(User $actor, SasaranStrategis $sasaran, string $alasan): array
    {
        $alasan = trim($alasan);

        $result = DB::transaction(function () use ($sasaran, $actor, $alasan) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::SASARAN_DELETE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Penghapusan sasaran strategis ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Penghapusan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::where('id', $sasaran->id)->lockForUpdate()->firstOrFail();

            if ($lockedSasaran->indikatorKinerjas()->exists()) {
                return [
                    'status' => 'has_children',
                    'dasarIzin' => $dasarIzin,
                ];
            }

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();
            $lockedSasaran->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $dasarIzin,
            );

            return [
                'status' => 'deleted',
                'nilaiLama' => $nilaiLama,
            ];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Penghapusan sasaran strategis ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );

            abort(403, 'Anda tidak berwenang menghapus sasaran strategis.');
        }

        if ($result['status'] === 'has_children') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus_ditolak',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: $sasaran->withoutRelations()->toArray(),
                nilaiBaru: null,
                alasan: "Penolakan penghapusan sasaran '{$sasaran->kode}': sasaran masih memiliki indikator kinerja. Alasan pengguna: {$alasan}",
                dasarIzin: $result['dasarIzin'],
            );

            throw ValidationException::withMessages([
                'sasaran' => "Sasaran '{$sasaran->kode}' tidak dapat dihapus karena masih memiliki indikator kinerja.",
            ]);
        }

        return $result['nilaiLama'];
    }
}
