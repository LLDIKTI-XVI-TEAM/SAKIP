<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Support\PermissionCodes;
use Illuminate\Support\Facades\DB;

class DestroyIndikator
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly ResolveLockedActor $lockedActor) {}

    /**
     * Mengarsipkan Indikator (never-delete mutlak, ADR 0003).
     *
     * Tidak ada jalur hapus fisik: baris selalu dipertahankan dengan
     * `status = arsip` beserta audit `indikator.arsipkan`, baik memiliki
     * dependensi maupun tidak. Idempoten — pemanggilan ulang pada baris
     * yang sudah diarsipkan tetap tercatat sebagai audit baru agar jejak
     * percobaan pengarsipan utuh. Reaktivasi (arsip → aktif) tidak
     * disediakan di PR ini.
     *
     * Keputusan izin dievaluasi ulang di dalam transaksi terkunci memakai
     * state terkini (anti-TOCTOU): pencabutan peran/grant/deny atau
     * penonaktifan akun di tengah jalan membuat operasi gagal tertutup.
     * Penolakan dicatat sebagai audit `indikator.hapus_ditolak` di luar
     * transaksi (agar tidak ikut rollback) lalu 403 dilempar.
     *
     * @return array{diarsipkan: bool, kode: string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, string $alasan): array
    {
        $alasan = trim($alasan);

        $result = DB::transaction(function () use ($indikator, $actor, $alasan) {
            // 1. Kunci dan muat ulang instance user aktor secara eksklusif (koordinasi dengan mutasi ACL)
            $kunci = $this->lockedActor->handle($actor, PermissionCodes::INDIKATOR_DELETE);
            /** @var User|null $lockedActor */
            $lockedActor = $kunci['aktor'];
            $currentDecision = $kunci['keputusan'];
            if (! $lockedActor || $lockedActor->status !== 'aktif') {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pengarsipan indikator kinerja ditolak karena akun pengguna tidak aktif.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            // 2. Evaluasi ulang keputusan izin di dalam transaksi yang terkunci memakai state terkini
            if (! $currentDecision->allowed) {
                return [
                    'status' => 'denied',
                    'alasan' => 'Pengarsipan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                    'dasarIzin' => $currentDecision->toAuditBasis(),
                ];
            }

            $dasarIzin = $currentDecision->toAuditBasis();

            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::where('id', $indikator->id)->lockForUpdate()->firstOrFail();
            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // Never-delete: arsipkan baris apa pun kondisinya, jangan hapus fisik.
            $lockedIndikator->update(['status' => IndikatorKinerja::STATUS_ARSIP]);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.arsipkan',
                objekTipe: 'indikator',
                objekId: (string) $lockedIndikator->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $lockedIndikator->withoutRelations()->toArray(),
                alasan: $alasan,
                dasarIzin: $dasarIzin,
            );

            return ['status' => 'archived', 'diarsipkan' => true, 'kode' => $nilaiLama['kode']];
        });

        if ($result['status'] === 'denied') {
            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'indikator.hapus_ditolak',
                objekTipe: 'indikator',
                objekId: (string) $indikator->id,
                nilaiLama: null,
                nilaiBaru: null,
                alasan: $result['alasan'] ?? 'Pengarsipan indikator kinerja ditolak karena wewenang tidak lagi berlaku saat transaksi.',
                dasarIzin: $result['dasarIzin'],
            );
            abort(403, 'Anda tidak berwenang mengarsipkan indikator kinerja.');
        }

        return ['diarsipkan' => true, 'kode' => $result['kode']];
    }
}
