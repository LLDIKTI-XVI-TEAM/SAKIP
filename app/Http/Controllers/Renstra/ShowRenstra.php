<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
use App\Models\Berkas;
use App\Models\Regulasi;
use App\Models\Renstra;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowRenstra extends Controller
{
    public function __invoke(Request $request, Renstra $renstra): Response
    {
        Gate::authorize('view', $renstra);

        $user = $request->user();
        $canViewAttachments = $user->can('viewAttachment', $renstra);
        $dapatBacaRegulasi = $user->can('viewAny', Regulasi::class);

        $relations = [
            'pembuat:id,nama',
        ];

        if ($dapatBacaRegulasi) {
            $relations[] = 'regulasi';
        }

        if ($canViewAttachments) {
            $relations['berkas'] = fn ($query) => $query->with('pengunggah:id,nama')->orderByDesc('created_at');
        }

        $renstra->load($relations);

        $canDeleteAttachment = $user->can('deleteAttachment', $renstra);
        $hasBerkas = $canViewAttachments && ! $canDeleteAttachment && $renstra->berkas()->exists();

        $detail = [
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
            'pembuat' => $renstra->pembuat ? [
                'id' => $renstra->pembuat->id,
                'nama' => $renstra->pembuat->nama,
            ] : null,
            'created_at' => $renstra->created_at?->toISOString(),
            'updated_at' => $renstra->updated_at?->toISOString(),
            'berkas' => $canViewAttachments
                ? $renstra->berkas->map(fn (Berkas $berkas): array => [
                    'id' => $berkas->id,
                    'mode' => $berkas->mode,
                    'nama_asli' => $berkas->nama_asli,
                    'mime' => $berkas->mime,
                    'ukuran_bytes' => $berkas->ukuran_bytes,
                    'tautan' => $berkas->tautan,
                    'isi_teks' => $berkas->isi_teks,
                    'download_url' => $berkas->mode === 'file'
                        ? route('renstra.berkas.download', [$renstra, $berkas])
                        : null,
                    'pengunggah' => $berkas->pengunggah ? [
                        'id' => $berkas->pengunggah->id,
                        'nama' => $berkas->pengunggah->nama,
                    ] : null,
                ])->values()->all()
                : [],
        ];

        if ($dapatBacaRegulasi) {
            $detail['regulasi'] = $renstra->regulasi ? [
                'id' => $renstra->regulasi->id,
                'jenis' => $renstra->regulasi->jenis,
                'nomor' => $renstra->regulasi->nomor,
                'tahun' => $renstra->regulasi->tahun,
                'tentang' => $renstra->regulasi->tentang,
            ] : null;
        }

        return Inertia::render('Renstra/Show', [
            'renstra' => $detail,
            'can' => [
                'update' => $user->can('update', $renstra) && $renstra->status !== Renstra::STATUS_DIARSIPKAN,
                'delete' => $user->can('delete', $renstra)
                    && ($canDeleteAttachment || ($canViewAttachments && ! $hasBerkas)),
                'deleteAttachment' => $canDeleteAttachment,
            ],
        ]);
    }
}
