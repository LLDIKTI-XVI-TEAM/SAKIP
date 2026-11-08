<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\Kegiatan\DaftarKegiatanRencanaAksi;
use App\Actions\RencanaAksi\IndexBuktiRencanaAksi;
use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ShowRencanaAksi extends Controller
{
    public function __invoke(Request $request, string $rencanaAksi, IndexRencanaAksi $index, IndexBuktiRencanaAksi $bukti, DaftarKegiatanRencanaAksi $kegiatan): Response
    {
        return DB::transaction(function () use ($request, $rencanaAksi, $index, $bukti, $kegiatan): Response {
            RencanaAksi::whereKey($rencanaAksi)->sharedLock()->firstOrFail();
            // Payload utama dievaluasi lebih dulu sehingga 404/403 header tetap dijawab IndexRencanaAksi.
            $payload = $index->handle($request->user(), $rencanaAksi);
            // Batas capability tambah kegiatan memakai periode yang dapat ditulis
            // dari payload yang sama; tanpa kunci tetap fail-closed.
            /** @var list<string> $periodeDapatDitulis */
            $periodeDapatDitulis = $payload['periode_dapat_ditulis'] ?? [];

            return Inertia::render('RencanaAksi/Show', [
                'rencanaAksi' => $payload,
                // Q63: panel bukti memakai status pengecualian yang sama dengan kartu kesiapan.
                'bukti' => $bukti->handle($request->user(), $rencanaAksi, (bool) ($payload['pengajuan']['dikecualikan'] ?? false)),
                'kegiatan' => $kegiatan->handle($request->user(), $rencanaAksi, $periodeDapatDitulis),
            ]);
        });
    }
}
