<?php

namespace App\Actions\Perencanaan;

use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DestroyIndikator
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly PermissionResolver $resolver) {}

    /**
     * Menghapus Indikator yang benar-benar tanpa riwayat, atau menonaktifkan
     * (`is_aktif = false`, setara `arsip` dokumen) bila memiliki dependensi.
     *
     * Cakupan dependensi (target, pengukuran, rencana aksi, komponen,
     * snapshot, penanggung jawab, jenis berkas) diperiksa di dalam transaksi
     * terkunci; pelanggaran FK restrict yang tak terduga menjadi fail-safe
     * nonaktivasi lewat savepoint. Otorisasi ditangani FormRequest/Policy di
     * batas request; di sini hanya mencatat dasar izin efektif.
     *
     * @return array{deactivated: bool, kode: string}
     */
    public function handle(User $actor, IndikatorKinerja $indikator, string $alasan): array
    {
        $alasan = trim($alasan);
        $dasarIzin = $this->resolver->resolve($actor, PermissionCodes::INDIKATOR_DELETE)->toAuditBasis();

        return DB::transaction(function () use ($indikator, $actor, $alasan, $dasarIzin) {
            /** @var IndikatorKinerja $lockedIndikator */
            $lockedIndikator = IndikatorKinerja::where('id', $indikator->id)->lockForUpdate()->firstOrFail();
            $nilaiLama = $lockedIndikator->withoutRelations()->toArray();

            // Cakup seluruh referensi dependensi ke indikator ini
            $hasDependencies = DB::table('target_kinerjas')->where('indikator_kinerja_id', $lockedIndikator->id)->exists()
                || DB::table('pengukuran_kinerjas')->where('indikator_id', $lockedIndikator->id)->exists()
                || DB::table('rencana_aksi')->where('indikator_id', $lockedIndikator->id)->exists()
                || DB::table('indikator_komponen')->where('indikator_id', $lockedIndikator->id)->exists()
                || DB::table('jadwal_snapshot')->where('indikator_id', $lockedIndikator->id)->exists()
                || DB::table('penanggung_jawab')->where('indikator_id', $lockedIndikator->id)->exists()
                || DB::table('jenis_berkas')->where('indikator_id', $lockedIndikator->id)->exists();

            if ($hasDependencies) {
                // Jangan hard-delete data yang memiliki dependensi/riwayat, nonaktifkan secara aman
                $lockedIndikator->update(['is_aktif' => false]);

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.nonaktifkan',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $lockedIndikator->withoutRelations()->toArray(),
                    alasan: $alasan,
                    dasarIzin: $dasarIzin,
                );

                return ['deactivated' => true, 'kode' => $nilaiLama['kode']];
            }

            try {
                // Gunakan nested transaction (savepoint) sebagai fail-safe bila terjadi pelanggaran FK restrict yang tak terduga
                DB::transaction(function () use ($lockedIndikator) {
                    $lockedIndikator->delete();
                });

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.hapus',
                    objekTipe: 'indikator',
                    objekId: (string) $lockedIndikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: null,
                    alasan: $alasan,
                    dasarIzin: $dasarIzin,
                );

                return ['deactivated' => false, 'kode' => $nilaiLama['kode']];
            } catch (QueryException $e) {
                // Fail-safe: jika ada constraint FK (SQLSTATE 23503), alihkan ke nonaktifkan
                if (($e->errorInfo[0] ?? null) === '23503') {
                    $lockedIndikator->update(['is_aktif' => false]);

                    $this->auditLogger->catat(
                        actor: $actor,
                        tindakan: 'indikator.nonaktifkan',
                        objekTipe: 'indikator',
                        objekId: (string) $lockedIndikator->id,
                        nilaiLama: $nilaiLama,
                        nilaiBaru: $lockedIndikator->withoutRelations()->toArray(),
                        alasan: $alasan,
                        dasarIzin: $dasarIzin,
                    );

                    return ['deactivated' => true, 'kode' => $nilaiLama['kode']];
                }

                throw $e;
            }
        });
    }
}
