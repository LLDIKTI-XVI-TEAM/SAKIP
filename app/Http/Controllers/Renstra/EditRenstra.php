<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Regulasi;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra): Response
    {
        Gate::authorize('view', $renstra);
        Gate::authorize('update', $renstra);

        if (! in_array($renstra->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true)) {
            abort(403, 'Renstra nonaktif atau diarsipkan hanya dapat dibaca.');
        }

        $user = $request->user();
        $dapatBacaRegulasi = $user !== null && $user->can('viewAny', Regulasi::class);

        if ($dapatBacaRegulasi) {
            $renstra->load('regulasi');
            $regulasiPilihan = Regulasi::query()
                ->where('aktif', true)
                ->orWhere('id', $renstra->regulasi_id)
                ->orderBy('tahun', 'desc')
                ->orderBy('nomor')
                ->get(['id', 'jenis', 'nomor', 'tahun', 'tentang']);
        } else {
            $renstra->unsetRelation('regulasi');
            $regulasiPilihan = [];
        }

        $canUploadAttachment = $renstra->status === Renstra::STATUS_DRAFT
            && $user !== null && $user->can('uploadAttachment', $renstra);

        return Inertia::render('Renstra/Edit', [
            'renstra' => [
                'id' => $renstra->id,
                'kode' => $renstra->kode,
                'nama' => $renstra->nama,
                'tahun_mulai' => $renstra->tahun_mulai,
                'tahun_selesai' => $renstra->tahun_selesai,
                'status' => $renstra->status,
                'is_aktif' => $renstra->is_aktif,
                'deskripsi' => $renstra->deskripsi,
                'dasar_hukum' => $renstra->dasar_hukum,
                'regulasi_id' => $dapatBacaRegulasi ? $renstra->regulasi_id : null,
                'regulasi' => $dapatBacaRegulasi && $renstra->regulasi ? $renstra->regulasi->only(['id', 'jenis', 'nomor', 'tahun', 'tentang']) : null,
                'pembuat' => null,
                'created_at' => $renstra->created_at?->toISOString(),
                'updated_at' => $renstra->updated_at?->toISOString(),
            ],
            'expected_state' => $renstra->stateToken(),
            'regulasiPilihan' => $regulasiPilihan,
            'can' => [
                'uploadAttachment' => $canUploadAttachment,
                'readRegulasi' => $dapatBacaRegulasi,
            ],
        ]);
    }
}
