<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShowVerifikasi extends Controller
{
    public function __invoke(Request $request, string $id, PresentPengukuran $present, PermissionResolver $resolver): Response
    {
        $pengukuran = PengukuranKinerja::with('jadwalSnapshot')->findOrFail($id);
        Gate::authorize('view', $pengukuran);
        $actor = $request->user();
        $unitId = $pengukuran->targetUnitId();
        abort_unless($resolver->allows($actor, 'pengukuran:verifikasi', $unitId)
            || $resolver->allows($actor, 'pengukuran:sahkan', $unitId)
            || $resolver->allows($actor, 'pengukuran:kembalikan', $unitId), 403);

        return Inertia::render('Verifikasi/Show', ['pengukuran' => $present->handle($pengukuran, $actor, true)]);
    }
}
