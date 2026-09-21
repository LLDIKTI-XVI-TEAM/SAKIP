<?php

namespace App\Http\Controllers\Regulasi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Regulasi\DeleteBerkasRegulasiRequest;
use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\User;
use App\Services\RegulasiService;
use Illuminate\Http\RedirectResponse;

class DestroyBerkasRegulasi extends Controller
{
    public function __invoke(
        DeleteBerkasRegulasiRequest $request,
        Regulasi $regulasi,
        Berkas $berkas,
        RegulasiService $regulasiService,
    ): RedirectResponse {
        abort_unless(
            $berkas->berkasable_type === $regulasi->getMorphClass()
                && $berkas->berkasable_id === $regulasi->id,
            404,
        );

        /** @var User $actor */
        $actor = $request->user();

        $regulasiService->deleteAttachment(
            regulasi: $regulasi,
            berkas: $berkas,
            alasan: $request->string('alasan')->toString(),
            actor: $actor,
        );

        return back()->with('success', 'Lampiran berhasil dihapus dan jejak audit telah dicatat.');
    }
}
