<?php

namespace App\Http\Controllers\Verifikasi;

use App\Actions\Pengukuran\IndexVerifikasi as IndexVerifikasiAction;
use App\Http\Controllers\Controller;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexVerifikasi extends Controller
{
    public function __invoke(Request $request, IndexVerifikasiAction $action, PermissionResolver $resolver): Response
    {
        $permissions = $this->permissions($request, $resolver);
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:diajukan,diverifikasi'],
            'renstra_id' => ['nullable', 'uuid'], 'tahun' => ['nullable', 'integer', 'between:1,9999'],
            'periode_id' => ['nullable', 'uuid'], 'unit_id' => ['nullable', 'uuid'],
        ]);
        $actor = $request->user();
        // Capability tautan ke antrean Disahkan; gerbang 403 halaman ini tetap ditentukan izin reviu.
        $bolehBukaKembali = $resolver->allows($actor, 'pengukuran:read') && $resolver->allows($actor, 'pengukuran:buka_kembali');

        return Inertia::render('Verifikasi/Index', ['can' => ['buka_kembali' => $bolehBukaKembali]]
            + $action->handle($actor, $permissions, $filters));
    }

    public function pilihan(Request $request, IndexVerifikasiAction $action, PermissionResolver $resolver): JsonResponse
    {
        $permissions = $this->permissions($request, $resolver);
        $input = $request->validate(['jenis' => ['required', 'in:renstra,periode,unit'],
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);

        return response()->json($action->pilihan($request->user(), $permissions, $input));
    }

    /** @return list<string> */
    private function permissions(Request $request, PermissionResolver $resolver): array
    {
        $actor = $request->user();
        $permissions = array_values(array_filter(['pengukuran:verifikasi', 'pengukuran:sahkan', 'pengukuran:kembalikan'], fn ($code) => $resolver->allows($actor, $code)));
        // Tanpa izin baca dan reviu selalu 403, sebelum memproses filter maupun lookup.
        abort_if($permissions === [] || ! $resolver->allows($actor, 'pengukuran:read'), 403);

        return $permissions;
    }
}
