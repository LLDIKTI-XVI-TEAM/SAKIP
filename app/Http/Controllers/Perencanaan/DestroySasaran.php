<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sasaran\DestroySasaranRequest;
use App\Models\SasaranStrategis;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PermissionCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class DestroySasaran extends Controller
{
    public function __invoke(DestroySasaranRequest $request, SasaranStrategis $sasaran, AuditLogger $auditLogger): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($sasaran->indikatorKinerjas()->exists()) {
            return redirect()
                ->back()
                ->withErrors(['sasaran' => "Sasaran '{$sasaran->kode}' tidak dapat dihapus karena masih memiliki indikator kinerja."]);
        }

        $renstraId = $sasaran->renstra_id;
        $nilaiLama = $sasaran->withoutRelations()->toArray();
        $alasan = $request->input('alasan') ?: "Menghapus sasaran strategis '{$sasaran->kode}'.";

        DB::transaction(function () use ($sasaran, $actor, $auditLogger, $nilaiLama, $alasan) {
            $sasaran->delete();

            $auditLogger->catat(
                actor: $actor,
                tindakan: 'sasaran.hapus',
                objekTipe: 'sasaran',
                objekId: (string) $sasaran->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: (string) $alasan,
                dasarIzin: ['permission' => PermissionCodes::SASARAN_DELETE],
            );
        });

        return redirect()
            ->route('perencanaan.sasaran-indikator.index', ['renstra_id' => $renstraId])
            ->with('success', "Sasaran strategis '{$nilaiLama['kode']}' berhasil dihapus.");
    }
}
