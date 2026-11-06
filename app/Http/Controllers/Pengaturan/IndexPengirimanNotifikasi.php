<?php

namespace App\Http\Controllers\Pengaturan;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifikasi\PembacaStatusPengiriman;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IndexPengirimanNotifikasi extends Controller
{
    /** Proyeksi berizin memakai pembaca existing; tidak mengekspos snapshot kontak atau pesan. */
    public function __invoke(Request $request, PembacaStatusPengiriman $pembaca): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        $filters = array_replace(
            ['status' => '', 'kanal' => '', 'penerima' => '', 'mulai' => '', 'selesai' => ''],
            $request->only(['status', 'kanal', 'penerima', 'mulai', 'selesai']),
        );
        $pengiriman = $pembaca->handle($actor, [...$filters, 'page' => $request->input('page')])
            ->appends($filters);

        return Inertia::render('Pengaturan/PengirimanNotifikasi', [
            'pengiriman' => $pengiriman,
            'filters' => array_map(fn ($value) => $value ?? '', $filters),
        ]);
    }
}
