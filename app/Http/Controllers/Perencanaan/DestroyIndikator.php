<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorRequest;
use App\Models\IndikatorKinerja;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DestroyIndikator extends Controller
{
    public function __invoke(DestroyIndikatorRequest $request, IndikatorKinerja $indikator, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $renstraId = SasaranStrategis::where('id', $indikator->sasaran_strategis_id)->value('renstra_id');
        $nilaiLama = $indikator->withoutRelations()->toArray();
        $alasan = $request->input('alasan') ?: "Menghapus indikator kinerja '{$indikator->kode}'.";

        $hasDependencies = DB::table('target_kinerjas')->where('indikator_kinerja_id', $indikator->id)->exists()
            || DB::table('pengukuran_kinerjas')->where('indikator_id', $indikator->id)->exists()
            || DB::table('rencana_aksi')->where('indikator_id', $indikator->id)->exists()
            || DB::table('indikator_komponen')->where('indikator_id', $indikator->id)->exists();

        DB::transaction(function () use ($indikator, $actor, $auditLogger, $nilaiLama, $alasan, $hasDependencies) {
            if ($hasDependencies) {
                // Jangan hard-delete data yang memiliki riwayat kinerja, nonaktifkan secara aman
                $indikator->update(['is_aktif' => false]);

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.nonaktifkan',
                    objekTipe: 'indikator',
                    objekId: (string) $indikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $indikator->withoutRelations()->toArray(),
                    alasan: "Menonaktifkan indikator kinerja '{$indikator->kode}' karena memiliki riwayat kinerja.",
                    dasarIzin: ['permission' => PermissionCodes::INDIKATOR_DELETE],
                );
            } else {
                $indikator->delete();

                $auditLogger->catat(
                    actor: $actor,
                    tindakan: 'indikator.hapus',
                    objekTipe: 'indikator',
                    objekId: (string) $indikator->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: null,
                    alasan: (string) $alasan,
                    dasarIzin: ['permission' => PermissionCodes::INDIKATOR_DELETE],
                );
            }
        });

        $message = $hasDependencies
            ? "Indikator kinerja '{$nilaiLama['kode']}' dinonaktifkan karena memiliki riwayat data kinerja."
            : "Indikator kinerja '{$nilaiLama['kode']}' berhasil dihapus.";

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', $message);
    }
}
