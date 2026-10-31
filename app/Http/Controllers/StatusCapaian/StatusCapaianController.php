<?php

namespace App\Http\Controllers\StatusCapaian;

use App\Actions\StatusCapaian\IndexStatusCapaian;
use App\Actions\StatusCapaian\TetapkanStatusCapaian;
use App\Http\Controllers\Controller;
use App\Http\Requests\StatusCapaian\StoreStatusCapaianRequest;
use App\Models\PengukuranKinerja;
use App\Models\StatusCapaian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Adapter halaman penetapan status capaian. Izin, unit target, token versi, dan seluruh aturan bisnis
 * diperiksa FormRequest dan Action; controller hanya meneruskan props allowlist atau redirect.
 */
class StatusCapaianController extends Controller
{
    public function index(Request $request, IndexStatusCapaian $action): Response
    {
        return Inertia::render('Verifikasi/StatusCapaian', $action->handle($request->user()));
    }

    public function store(StoreStatusCapaianRequest $request, PengukuranKinerja $pengukuran, TetapkanStatusCapaian $action): RedirectResponse
    {
        $row = $action->handle($request->user(), $pengukuran->id, $request->validated());

        return back()->with('success', $row->status === StatusCapaian::STATUS_TERCAPAI
            ? 'Status capaian ditetapkan sebagai Tercapai.'
            : 'Status capaian ditetapkan sebagai Belum tercapai.');
    }
}
