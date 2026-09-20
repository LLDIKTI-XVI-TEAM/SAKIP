<?php

namespace App\Http\Controllers\JenisBerkas;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JenisBerkasController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('jenis_berkas:read');

        $jenisBerkasList = JenisBerkas::with('indikator')
            ->orderBy('tahap')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();

        $indikators = IndikatorKinerja::where('is_aktif', true)
            ->select('id', 'kode', 'nama')
            ->orderBy('kode')
            ->get();

        return Inertia::render('JenisBerkas/Index', [
            'jenisBerkasList' => $jenisBerkasList,
            'indikators' => $indikators,
            'can' => [
                'create' => $request->user()->can('jenis_berkas:create'),
                'update' => $request->user()->can('jenis_berkas:update'),
                'delete' => $request->user()->can('jenis_berkas:delete'),
            ],
        ]);
    }

    public function store(StoreJenisBerkasRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;

        $jb = JenisBerkas::create($data);

        AuditLogger::catat(
            actor: $request->user(),
            tindakan: 'jenis_berkas.buat',
            objekTipe: 'jenis_berkas',
            objekId: $jb->id,
            nilaiLama: null,
            nilaiBaru: $jb->toArray(),
            alasan: null
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');
    }

    public function update(UpdateJenisBerkasRequest $request, string $id): RedirectResponse
    {
        $jb = JenisBerkas::findOrFail($id);
        $nilaiLama = $jb->toArray();

        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        $jb->update($data);

        AuditLogger::catat(
            actor: $request->user(),
            tindakan: 'jenis_berkas.ubah',
            objekTipe: 'jenis_berkas',
            objekId: $jb->id,
            nilaiLama: $nilaiLama,
            nilaiBaru: $jb->fresh()->toArray(),
            alasan: $alasan
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id): RedirectResponse
    {
        $jb = JenisBerkas::findOrFail($id);
        $nilaiLama = $jb->toArray();
        $alasan = $request->validated()['alasan'];

        $jb->delete();

        AuditLogger::catat(
            actor: $request->user(),
            tindakan: 'jenis_berkas.hapus',
            objekTipe: 'jenis_berkas',
            objekId: $id,
            nilaiLama: $nilaiLama,
            nilaiBaru: null,
            alasan: $alasan
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');
    }
}
