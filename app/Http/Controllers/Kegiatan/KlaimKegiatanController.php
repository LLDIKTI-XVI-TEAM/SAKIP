<?php

namespace App\Http\Controllers\Kegiatan;

use App\Actions\Kegiatan\HapusKlaimKegiatan;
use App\Actions\Kegiatan\TambahKlaimKegiatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kegiatan\HapusKlaimKegiatanRequest;
use App\Http\Requests\Kegiatan\TambahKlaimKegiatanRequest;
use App\Models\Kegiatan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class KlaimKegiatanController extends Controller
{
    public function store(TambahKlaimKegiatanRequest $request, Kegiatan $kegiatan, TambahKlaimKegiatan $action): RedirectResponse
    {
        $action->handle($request->user(), (string) $kegiatan->id, $request->validated());

        return back()->with('success', 'Klaim kegiatan berhasil ditambahkan.');
    }

    public function destroy(HapusKlaimKegiatanRequest $request, Kegiatan $kegiatan, string $klaim, HapusKlaimKegiatan $action): RedirectResponse
    {
        $hasil = $action->handle($request->user(), (string) $kegiatan->id, $klaim, (string) $request->validated('alasan'));

        // Konteks RA yang tidak lagi memuat kegiatan ini tidak dapat dibuka
        // (guard halaman ubah menjawab 404), jadi pengguna dikembalikan ke
        // daftar kegiatan RA tersebut, bukan ke URL yang sudah tidak berlaku.
        $konteks = $this->konteksRencanaAksi($request, (string) $kegiatan->id);
        if ($konteks !== null && $hasil['sisa_klaim'] === 0 && Str::lower($konteks) === Str::lower($hasil['rencana_aksi_id'])) {
            return redirect()
                ->route('rencana-aksi.kegiatan.index', ['rencanaAksi' => $hasil['rencana_aksi_id']])
                ->with('success', 'Klaim kegiatan berhasil dihapus.');
        }

        return back()->with('success', 'Klaim kegiatan berhasil dihapus.');
    }

    /**
     * Konteks RA halaman asal: field eksplisit dari form bila dikirim, selain
     * itu path `Referer` halaman ubah RA kegiatan yang sama. `null` bila tidak
     * ada yang cocok agar pengalihan kembali ke `back()` biasa.
     */
    private function konteksRencanaAksi(HapusKlaimKegiatanRequest $request, string $kegiatanId): ?string
    {
        $eksplisit = $request->validated('rencana_aksi_konteks');
        if (is_string($eksplisit) && Str::isUuid($eksplisit)) {
            return $eksplisit;
        }

        $path = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }
        if (preg_match('#/rencana-aksi/([^/]+)/kegiatan/'.preg_quote($kegiatanId, '#').'$#', $path, $cocok) !== 1) {
            return null;
        }

        return Str::isUuid($cocok[1]) ? $cocok[1] : null;
    }
}
