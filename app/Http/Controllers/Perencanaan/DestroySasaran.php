<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\DestroySasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PermissionResolver;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DestroySasaran extends Controller
{
    public function __invoke(
        DestroySasaranRequest $request,
        SasaranStrategis $sasaran,
        AuditLogger $auditLogger,
        PermissionResolver $resolver
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $renstraId = $sasaran->renstra_id;
        $alasan = trim((string) $request->validated('alasan'));
        $dasarIzin = $resolver->resolve($actor, PermissionCodes::SASARAN_DELETE)->toAuditBasis();

        $nilaiLama = DB::transaction(function () use ($sasaran, $actor, $auditLogger, $alasan, $dasarIzin) {
            /** @var SasaranStrategis $lockedSasaran */
            $lockedSasaran = SasaranStrategis::where('id', $sasaran->id)->lockForUpdate()->firstOrFail();

            if ($lockedSasaran->indikatorKinerjas()->exists()) {
                throw ValidationException::withMessages([
                    'sasaran' => "Sasaran '{$lockedSasaran->kode}' tidak dapat dihapus karena masih memiliki indikator kinerja.",
                ]);
            }

            $nilaiLama = $lockedSasaran->withoutRelations()->toArray();
            $lockedSasaran->delete();

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus',
                objekTipe: 'sasaran',
                objekId: (string) $lockedSasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $dasarIzin,
            );

            return $nilaiLama;
        });

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Sasaran strategis '{$nilaiLama['kode']}' berhasil dihapus.");
    }
}
