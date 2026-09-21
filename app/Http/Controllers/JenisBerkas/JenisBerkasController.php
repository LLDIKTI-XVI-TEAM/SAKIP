<?php

namespace App\Http\Controllers\JenisBerkas;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteJenisBerkasRequest;
use App\Http\Requests\StoreJenisBerkasRequest;
use App\Http\Requests\UpdateJenisBerkasRequest;
use App\Models\IndikatorKinerja;
use App\Models\JenisBerkas;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JenisBerkasController extends Controller
{
    public function index(Request $request, PermissionResolver $resolver): Response
    {
        $actor = $request->user()->fresh();
        abort_unless($resolver->allows($actor, 'jenis_berkas:read'), 403);

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
                'create' => $resolver->allows($actor, 'jenis_berkas:create'),
                'update' => $resolver->allows($actor, 'jenis_berkas:update'),
                'delete' => $resolver->allows($actor, 'jenis_berkas:delete'),
            ],
        ]);
    }

    public function store(StoreJenisBerkasRequest $request, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        abort_unless($resolver->allows($actor, 'jenis_berkas:create'), 403);

        $data = $request->validated();
        $data['created_by'] = $actor->id;

        $jb = JenisBerkas::create($data);

        AuditLogger::catat(
            actor: $actor,
            tindakan: 'jenis_berkas.buat',
            objekTipe: 'jenis_berkas',
            objekId: $jb->id,
            nilaiLama: null,
            nilaiBaru: $jb->toArray(),
            alasan: 'Penambahan persyaratan jenis berkas: '.$jb->nama,
            dasarIzin: ['permission' => 'jenis_berkas:create']
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');
    }

    public function update(UpdateJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        abort_unless($resolver->allows($actor, 'jenis_berkas:update'), 403);

        $jb = JenisBerkas::findOrFail($id);
        $nilaiLama = $jb->toArray();

        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        $jb->update($data);

        AuditLogger::catat(
            actor: $actor,
            tindakan: 'jenis_berkas.ubah',
            objekTipe: 'jenis_berkas',
            objekId: $jb->id,
            nilaiLama: $nilaiLama,
            nilaiBaru: $jb->fresh()->toArray(),
            alasan: $alasan,
            dasarIzin: ['permission' => 'jenis_berkas:update']
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        abort_unless($resolver->allows($actor, 'jenis_berkas:delete'), 403);

        $jb = JenisBerkas::findOrFail($id);
        $nilaiLama = $jb->toArray();
        $alasan = $request->validated()['alasan'];

        $jb->delete();

        AuditLogger::catat(
            actor: $actor,
            tindakan: 'jenis_berkas.hapus',
            objekTipe: 'jenis_berkas',
            objekId: $id,
            nilaiLama: $nilaiLama,
            nilaiBaru: null,
            alasan: $alasan,
            dasarIzin: ['permission' => 'jenis_berkas:delete']
        );

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');
    }
}
