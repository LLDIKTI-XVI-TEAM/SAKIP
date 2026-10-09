<?php

namespace App\Http\Controllers\RencanaAksi;

use App\Actions\RencanaAksi\AppendRencanaAksiEvidence;
use App\Actions\RencanaAksi\DeleteRencanaAksiEvidence;
use App\Actions\RencanaAksi\EvaluateRencanaAksiEvidence;
use App\Http\Controllers\Controller;
use App\Http\Requests\RencanaAksi\DestroyRencanaAksiEvidenceRequest;
use App\Http\Requests\RencanaAksi\StoreRencanaAksiEvidenceRequest;
use App\Models\Berkas;
use App\Models\RencanaAksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RencanaAksiEvidenceController extends Controller
{
    public function __construct(
        protected EvaluateRencanaAksiEvidence $evaluator,
    ) {}

    /**
     * Menampilkan halaman pemenuhan bukti dukung Rencana Aksi.
     */
    public function index(Request $request, RencanaAksi $rencanaAksi): Response
    {
        Gate::authorize('viewEvidence', $rencanaAksi);

        $rencanaAksi->loadMissing(['indikator', 'unit', 'penanggungJawab']);

        $persyaratan = $this->evaluator->handle($rencanaAksi);
        $summary = $this->evaluator->summary($rencanaAksi, $persyaratan);
        $settings = $this->evaluator->settings();

        $daftarBukti = $rencanaAksi->buktiDukungs()
            ->current()
            ->with(['jenisBerkas:id,nama', 'pengunggah:id,nama'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Berkas $b) => [
                'id' => $b->id,
                'jenis_berkas_id' => $b->jenis_berkas_id,
                'nama_persyaratan' => $b->jenisBerkas?->nama,
                'mode' => $b->mode,
                'nama_asli' => $b->nama_asli,
                'mime' => $b->mime,
                'ukuran_bytes' => $b->ukuran_bytes,
                'tautan' => $b->tautan,
                'isi_teks' => $b->isi_teks,
                'menggantikan_id' => $b->menggantikan_id,
                'alasan_koreksi' => $b->alasan_koreksi,
                'uploaded_by' => $b->uploaded_by,
                'pengunggah_nama' => $b->pengunggah?->nama,
                'created_at' => $b->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        return Inertia::render('RencanaAksi/BuktiIndex', [
            'rencanaAksi' => [
                'id' => $rencanaAksi->id,
                'tahun' => $rencanaAksi->tahun,
                'uraian' => $rencanaAksi->uraian,
                'status_alur' => $rencanaAksi->status_alur,
                'versi' => $rencanaAksi->versi,
                'is_disahkan' => $rencanaAksi->isDisahkan(),
                'indikator' => $rencanaAksi->indikator ? [
                    'id' => $rencanaAksi->indikator->id,
                    'kode' => $rencanaAksi->indikator->kode,
                    'nama' => $rencanaAksi->indikator->nama,
                    'satuan' => $rencanaAksi->indikator->satuan,
                ] : null,
                'unit' => $rencanaAksi->unit ? [
                    'id' => $rencanaAksi->unit->id,
                    'nama' => $rencanaAksi->unit->nama,
                ] : null,
                'penanggungJawab' => $rencanaAksi->penanggungJawab ? [
                    'id' => $rencanaAksi->penanggungJawab->id,
                    'nama' => $rencanaAksi->penanggungJawab->nama,
                ] : null,
            ],
            'persyaratan' => $persyaratan,
            'summary' => $summary,
            'daftarBukti' => $daftarBukti,
            'storageSettings' => $settings,
            'can' => [
                'upload' => $request->user()?->can('uploadEvidence', $rencanaAksi) ?? false,
                'delete' => $request->user()?->can('deleteEvidence', $rencanaAksi) ?? false,
            ],
        ]);
    }

    /**
     * Menyimpan pemenuhan bukti dukung baru (file/tautan/teks).
     */
    public function store(
        StoreRencanaAksiEvidenceRequest $request,
        RencanaAksi $rencanaAksi,
        AppendRencanaAksiEvidence $action,
    ): RedirectResponse {
        $data = $request->validated();
        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file');
        }

        $action->handle(
            $rencanaAksi,
            $data,
            $request->user(),
        );

        return back()->with('success', 'Bukti dukung Rencana Aksi berhasil disimpan.');
    }

    /**
     * Menghapus bukti dukung dari Rencana Aksi.
     */
    public function destroy(
        DestroyRencanaAksiEvidenceRequest $request,
        RencanaAksi $rencanaAksi,
        Berkas $bukti,
        DeleteRencanaAksiEvidence $action,
    ): RedirectResponse {
        $action->handle(
            $rencanaAksi,
            $bukti,
            $request->validated('alasan'),
            $request->user(),
        );

        return back()->with('success', 'Bukti dukung Rencana Aksi berhasil dihapus.');
    }
}
