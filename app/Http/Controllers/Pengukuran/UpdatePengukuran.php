<?php

namespace App\Http\Controllers\Pengukuran;

use App\Actions\Pengukuran\SaveDraftPengukuran;
use App\Actions\Pengukuran\SubmitPengukuran;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengukuran\SavePengukuranRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class UpdatePengukuran extends Controller
{
    public function __invoke(SavePengukuranRequest $request, string $id, SaveDraftPengukuran $draft, SubmitPengukuran $submit): RedirectResponse
    {
        $data = $request->validated();
        $submitting = $data['action'] === 'ajukan';
        try {
            ($submitting ? $submit : $draft)->handle($request->user(), $id, $data);
        } catch (ValidationException $exception) {
            if (! $submitting || $request->expectsJson()) {
                throw $exception;
            }

            // Semua alasan percobaan dipertahankan; adapter Inertia merangkum error field menjadi satu pesan.
            return redirect()->route('pengukuran.edit', $id)->withErrors($exception->errors(), $exception->errorBag)
                ->with('hasil_pengajuan_terakhir', ['pengukuran_id' => $id, 'actor_id' => $request->user()->id,
                    'alasan' => array_merge(...array_values($exception->errors()))]);
        }

        return redirect()->route('pengukuran.index')->with('success', $submitting ? 'Pengukuran berhasil diajukan untuk verifikasi.' : 'Draf pengukuran berhasil disimpan.');
    }
}
