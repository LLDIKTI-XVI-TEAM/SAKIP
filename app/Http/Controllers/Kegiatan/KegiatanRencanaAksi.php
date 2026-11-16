<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\DaftarKegiatanRencanaAksi;
use App\Actions\RencanaAksi\IndexRencanaAksi;
use App\Http\Controllers\Controller;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanRencanaAksi extends Controller
{
    /**
     * Halaman kegiatan satu Rencana Aksi: satu-satunya permukaan tambah, ubah,
     * klaim, dan berkas kegiatan (Q74 §74.1). Payload header dievaluasi lebih
     * dulu agar 404/403 tetap dijawab `IndexRencanaAksi`; daftar kegiatan null
     * (tanpa data) bila aktor tidak berhak membaca kegiatan unit.
     */
    public function __invoke(Request $request, RencanaAksi $rencanaAksi, IndexRencanaAksi $index, DaftarKegiatanRencanaAksi $kegiatan): Response
    {
        // Payload utama dimuat lebih dulu sehingga penolakan header tidak didahului query kegiatan.
        $payload = $index->handle($request->user(), (string) $rencanaAksi->id);
        // Batas capability tambah berasal dari payload Rencana Aksi (aturan yang
        // sama dengan gerbang tulis); tanpa kunci tetap fail-closed.
        /** @var list<string> $periodeDapatDitulis */
        $periodeDapatDitulis = $payload['periode_dapat_ditulis'] ?? [];

        return Inertia::render('RencanaAksi/Kegiatan', [
            'rencanaAksi' => $payload,
            'kegiatan' => $kegiatan->handle($request->user(), (string) $rencanaAksi->id, $periodeDapatDitulis),
        ]);
    }
}
