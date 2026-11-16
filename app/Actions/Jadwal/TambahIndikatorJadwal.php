<?php

namespace App\Actions\Jadwal;

use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Jadwal\JadwalActivationReadiness;
use App\Services\Jadwal\JadwalSnapshotWriter;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TambahIndikatorJadwal
{
    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit,
        private readonly JadwalActivationReadiness $readiness, private readonly JadwalSnapshotWriter $snapshots) {}

    /**
     * Menambah satu indikator ke jadwal aktif tanpa transisi; jadwal tetap `aktif`.
     * Yang ditulis hanya satu snapshot versi 1, komponennya, dan audit. Baris jadwal, jendela
     * periode, dan snapshot lama tidak disentuh, sehingga kewajiban yang sudah berjalan tetap sama.
     * Urutan lock: aktor → konfigurasi periode shared → indikator → Jadwal shared → target → komponen aktif.
     *
     * @param  array{indikator_id: string, periode_mulai_id: string, alasan: string}  $data
     */
    public function handle(User $actor, string $jadwalId, array $data): void
    {
        // UUID huruf kapital di URL/body dinormalisasi agar pencocokan dan audit memakai bentuk yang tersimpan.
        $jadwalId = strtolower($jadwalId);
        $indikatorId = strtolower($data['indikator_id']);
        $periodeMulaiId = strtolower($data['periode_mulai_id']);
        /** @var array<string, array<string, mixed>> $basis */
        $basis = [];
        try {
            DB::transaction(function () use ($actor, $jadwalId, $indikatorId, $periodeMulaiId, $data, &$basis): void {
                // Dicek ulang di sini agar pemanggilan tanpa FormRequest tetap memerlukan kedua izin.
                foreach ([PermissionCodes::JADWAL_BUKA_KEMBALI, PermissionCodes::INDIKATOR_READ] as $kode) {
                    $basis[$kode] = $this->lockedActor->handle($actor, $kode)['keputusan']->toAuditBasis();
                }
                if ($basis[PermissionCodes::JADWAL_BUKA_KEMBALI]['keputusan'] !== 'diizinkan' || $basis[PermissionCodes::INDIKATOR_READ]['keputusan'] !== 'diizinkan') {
                    throw new AuthorizationException('Anda tidak memiliki izin menambahkan indikator ke jadwal berjalan.');
                }
                Periode::lockConfiguration();
                // ID di body yang tidak ada diperlakukan sebagai indikator tidak layak, bukan 404.
                $locked = IndikatorKinerja::whereKey($indikatorId)->lockForUpdate()->first();
                $jadwal = JadwalTahunan::whereKey($jadwalId)->sharedLock()->firstOrFail();
                if ($jadwal->status !== 'aktif' || $jadwal->tahunDitutup()) {
                    throw ValidationException::withMessages(['jadwal' => 'Indikator hanya dapat ditambahkan pada jadwal aktif yang belum melewati tanggal penutupan.']);
                }
                TargetKinerja::where('indikator_kinerja_id', $indikatorId)->where('tahun', $jadwal->tahun)->orderBy('id')->lockForUpdate()->get(['id']);
                IndikatorKomponen::where('indikator_id', $indikatorId)->where('aktif', true)->orderBy('id')->lockForUpdate()->get(['id']);

                $context = $this->readiness->context($jadwal);
                $tolak = fn (string $pesan): ValidationException => ValidationException::withMessages(['indikator_id' => $pesan]);
                if ($locked !== null && in_array($locked->id, $context['snapshotted'], true)) {
                    throw $tolak('Indikator sudah masuk jadwal ini.');
                }
                // Instance dari context membawa komponen aktif terurut yang dibaca validasi definisi dan salinan snapshot.
                $indikator = $context['baru']->first(fn (IndikatorKinerja $row): bool => $row->id === $locked?->id);
                if ($indikator === null) {
                    throw $tolak('Indikator tidak aktif, belum berlaku pada tahun jadwal, atau bukan bagian Renstra jadwal ini.');
                }
                if (! $this->readiness->dapatDibekukan($indikator)) {
                    throw $tolak('Data indikator tidak dapat dibekukan (nama terlalu panjang atau komponen tidak sesuai tipe perhitungan). Perbaiki pada modul indikator.');
                }
                $target = $context['targets']->get($indikator->id);
                if ($target === null || $target->target_tahunan === null) {
                    throw $tolak('Target tahunan indikator pada Perjanjian Kinerja belum diisi.');
                }
                if (! $jadwal->periode->contains('periode_id', $periodeMulaiId)) {
                    throw ValidationException::withMessages(['periode_mulai_id' => 'Periode mulai berlaku harus salah satu periode jadwal ini.']);
                }

                $this->snapshots->bekukan($actor, $jadwal, $indikator, $target, $periodeMulaiId, AuditReason::sanitize($data['alasan']), $basis);
            });
        } catch (AuthorizationException|ValidationException|QueryException|DeadlockException $exception) {
            // Deadlock/serialization/lock timeout menjadi konflik aman; QueryException lain tetap error internal.
            // Di transaksi bertingkat Laravel membungkus deadlock sebagai DeadlockException.
            if ($exception instanceof QueryException || $exception instanceof DeadlockException) {
                if ($exception instanceof QueryException && ! in_array($exception->errorInfo[0] ?? null, ['40P01', '40001', '55P03'], true)) {
                    throw $exception;
                }
                $exception = ValidationException::withMessages(['jadwal' => 'Data sedang diubah pengguna lain. Muat ulang detail jadwal lalu coba lagi.']);
            }
            // Di luar rollback, satu kali per percobaan, memakai keputusan izin yang benar-benar dipakai.
            $this->audit->catat(actor: $actor, tindakan: 'jadwal.tambah_indikator_ditolak', objekTipe: 'jadwal', objekId: $jadwalId,
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_penambahan'], dasarIzin: $basis !== [] ? $basis : null);
            throw $exception;
        }
    }
}
