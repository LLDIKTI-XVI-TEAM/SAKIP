<?php

namespace App\Http\Controllers\Renstra;

use App\Http\Controllers\Controller;
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
            $relations['berkas'] = fn ($query) => $query->with('pengunggah')->orderByDesc('created_at');
        } else {
            $renstra->setRelation('berkas', collect([]));
        }

        $renstra->load($relations);

        if (! $dapatBacaRegulasi) {
            $renstra->unsetRelation('regulasi');
        }

        return Inertia::render('Renstra/Show', [
            'renstra' => $renstra,
            'can' => [
                'update' => $user->can('update', $renstra) && $renstra->status !== Renstra::STATUS_DIARSIPKAN,
                'delete' => $user->can('delete', $renstra),
                'deleteAttachment' => $user->can('deleteAttachment', $renstra),
            ],
        ]);
    }
}
