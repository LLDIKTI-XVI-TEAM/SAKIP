<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\IndexVerifikasi as IndexVerifikasiAction;
use App\Http\Controllers\Controller;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexVerifikasi extends Controller
{
    public function __invoke(Request $request, IndexVerifikasiAction $action, PermissionResolver $resolver): Response
    {
        $actor = $request->user();
        $permissions = array_values(array_filter(['pengukuran:verifikasi', 'pengukuran:sahkan', 'pengukuran:kembalikan'], fn ($code) => $resolver->allows($actor, $code)));
        // Otorisasi sebelum validasi `page`: tanpa izin reviu selalu 403.
        abort_if($permissions === [] || ! $resolver->allows($actor, 'pengukuran:read'), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('Verifikasi/Index', $action->handle($actor, $permissions));
    }
}
