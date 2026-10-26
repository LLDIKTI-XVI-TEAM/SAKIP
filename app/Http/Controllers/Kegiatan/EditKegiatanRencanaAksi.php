<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\DaftarBerkasPerencanaanKegiatan;
use App\Actions\Kegiatan\DaftarKlaimKegiatan;
use App\Actions\Kegiatan\FormKegiatan;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\RencanaAksi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditKegiatanRencanaAksi extends Controller
{
    /**
     * Form ubah kegiatan dalam konteks Rencana Aksi pengklaimnya (Q74 §74.1).
     * `FormKegiatan` menegakkan `kegiatan:read` + `kegiatan:update` per unit dan
     * mengaudit penolakan; gerbang itu dievaluasi lebih dulu agar pengguna tanpa
     * izin tidak dapat membedakan kegiatan yang diklaim RA ini dari yang bukan.
     */
    public function __invoke(Request $request, RencanaAksi $rencanaAksi, Kegiatan $kegiatan,
        FormKegiatan $form, DaftarKlaimKegiatan $klaim, DaftarBerkasPerencanaanKegiatan $berkas): Response
    {
        // Form dievaluasi lebih dulu agar penolakan akses tetap dijawab FormKegiatan.
        $props = $form->handle($request->user(), $kegiatan);

        // Konteks RA: kegiatan di luar klaim RA ini diperlakukan tidak ada, sehingga
        // props kegiatan tidak dapat dibuka lewat UUID Rencana Aksi lain.
        abort_unless(
            KlaimKegiatan::where('kegiatan_id', $kegiatan->id)->where('rencana_aksi_id', $rencanaAksi->id)->exists(),
            404,
        );

        return Inertia::render('RencanaAksi/KegiatanEdit', $props + [
            // Identitas RA untuk tautan kembali; label indikator tersedia dari panel klaim.
            'rencanaAksi' => $rencanaAksi->only(['id', 'tahun']),
            'klaim' => $klaim->handle($request->user(), $kegiatan),
            'berkasPerencanaan' => $berkas->handle($request->user(), $kegiatan),
        ]);
    }
}
