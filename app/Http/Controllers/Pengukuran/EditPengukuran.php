<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\PresentPengukuran;
use App\Http\Controllers\Controller;
use App\Models\PengukuranKinerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EditPengukuran extends Controller
{
    public function __invoke(Request $request, string $id, PresentPengukuran $present): Response
    {
        $pengukuran = PengukuranKinerja::findOrFail($id);
        Gate::authorize('view', $pengukuran);
        $result = $request->session()->get('hasil_pengajuan_terakhir');
        $lastSubmission = is_array($result) && ($result['pengukuran_id'] ?? null) === $id && ($result['actor_id'] ?? null) === $request->user()->id
            ? ['alasan' => $result['alasan']] : null;

        return Inertia::render('Pengukuran/Edit', ['pengukuran' => $present->handle($pengukuran, $request->user(), true),
            'hasil_pengajuan_terakhir' => $lastSubmission]);
    }
}
