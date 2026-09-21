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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $referencedIndikatorIds = JenisBerkas::whereNotNull('indikator_id')->pluck('indikator_id')->all();

        $indikators = IndikatorKinerja::where('is_aktif', true)
            ->orWhereIn('id', $referencedIndikatorIds)
            ->select('id', 'kode', 'nama', 'is_aktif')
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
        $decision = $resolver->decide($actor, 'jenis_berkas:create');
        abort_unless($decision['allowed'], 403);

        DB::transaction(function () use ($request, $actor, $decision) {
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
                dasarIzin: $decision
            );
        });

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil ditambahkan.');
    }

    public function update(UpdateJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'jenis_berkas:update');
        abort_unless($decision['allowed'], 403);

        $jb = JenisBerkas::findOrFail($id);

        $expectedUpdatedAt = $request->validated()['expected_updated_at'] ?? null;
        if ($expectedUpdatedAt !== null && $jb->updated_at !== null) {
            $expectedTimestamp = Carbon::parse($expectedUpdatedAt)->timestamp;
            if ($jb->updated_at->timestamp !== $expectedTimestamp) {
                throw ValidationException::withMessages([
                    'konflik' => 'Data persyaratan telah diperbarui oleh pengguna lain. Silakan muat ulang halaman untuk melihat perubahan terkini.',
                ]);
            }
        }

        DB::transaction(function () use ($request, $jb, $actor, $decision) {
            $nilaiLama = $jb->toArray();

            $data = $request->validated();
            $alasan = $data['alasan'];
            unset($data['alasan'], $data['expected_updated_at']);

            $jb->update($data);

            AuditLogger::catat(
                actor: $actor,
                tindakan: 'jenis_berkas.ubah',
                objekTipe: 'jenis_berkas',
                objekId: $jb->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $jb->fresh()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision
            );
        });

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil diperbarui.');
    }

    public function destroy(DeleteJenisBerkasRequest $request, string $id, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'jenis_berkas:delete');
        abort_unless($decision['allowed'], 403);

        DB::transaction(function () use ($request, $id, $actor, $decision) {
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
                dasarIzin: $decision
            );
        });

        return redirect()->route('jenis-berkas.index')->with('success', 'Persyaratan jenis berkas berhasil dihapus.');
    }
}
