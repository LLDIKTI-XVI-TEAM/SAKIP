<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DestroyIndikator extends Controller
{
    public function __invoke(
        DestroyIndikatorRequest $request,
        IndikatorKinerja $indikator,
        AuditLogger $auditLogger,
        PermissionResolver $resolver
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');
        $alasan = trim((string) $request->validated('alasan'));
        $dasarIzin = $resolver->resolve($actor, PermissionCodes::INDIKATOR_DELETE)->toAuditBasis();

        $result = DB::transaction(function () use ($indikator, $actor, $auditLogger, $alasan, $dasarIzin) {
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

                $auditLogger->catat(
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

                $auditLogger->catat(
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

                    $auditLogger->catat(
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

        $message = $result['deactivated']
            ? "Indikator kinerja '{$result['kode']}' dinonaktifkan karena memiliki riwayat data kinerja."
            : "Indikator kinerja '{$result['kode']}' berhasil dihapus.";

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', $message);
    }
}
