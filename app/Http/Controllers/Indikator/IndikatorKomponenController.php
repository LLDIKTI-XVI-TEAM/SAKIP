<?php

namespace App\Http\Controllers\Indikator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorKomponenRequest;
use App\Http\Requests\Indikator\StoreIndikatorKomponenRequest;
use App\Http\Requests\Indikator\UpdateIndikatorKomponenRequest;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Kinerja\IndikatorPerhitunganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IndikatorKomponenController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IndikatorPerhitunganService $perhitunganService,
    ) {}

    public function index(Request $request, IndikatorKinerja $indikator, PermissionResolver $resolver): Response
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:read');
        abort_unless($decision['allowed'], 403, 'Akses membaca konfigurasi komponen ditolak.');

        $indikator->load(['sasaranStrategis.renstra', 'unit']);
        $komponenList = $indikator->komponen()->orderBy('urutan')->get();

        $contract = $this->perhitunganService->getFormulaContract($indikator);
        $validation = $this->perhitunganService->validateDefinisiKomponen($indikator);

        return Inertia::render('Indikator/Komponen/Index', [
            'indikator' => $indikator,
            'komponen' => $komponenList,
            'formulaContract' => $contract,
            'validation' => $validation,
            'can' => [
                'create' => $resolver->allows($actor, 'komponen:create'),
                'update' => $resolver->allows($actor, 'komponen:update'),
                'delete' => $resolver->allows($actor, 'komponen:delete'),
            ],
        ]);
    }

    public function store(StoreIndikatorKomponenRequest $request, IndikatorKinerja $indikator, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:create');
        abort_unless($decision['allowed'], 403, 'Akses penambahan komponen ditolak.');

        $data = $request->validated();
        $data['indikator_id'] = $indikator->id;
        $data['created_by'] = $actor->id;

        DB::transaction(function () use ($data, $actor, $decision) {
            $komponen = IndikatorKomponen::create($data);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'komponen.buat',
                objekTipe: 'indikator_komponen',
                objekId: $komponen->id,
                nilaiLama: null,
                nilaiBaru: $komponen->toArray(),
                alasan: 'Penambahan komponen indikator '.$komponen->kode.' ('.$komponen->label.')',
                dasarIzin: $decision,
            );
        });

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil ditambahkan.');
    }

    public function update(UpdateIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        abort_if($komponen->indikator_id !== $indikator->id, 404);

        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:update');
        abort_unless($decision['allowed'], 403, 'Akses pembaruan komponen ditolak.');

        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        DB::transaction(function () use ($komponen, $data, $actor, $alasan, $decision) {
            $nilaiLama = $komponen->toArray();
            $komponen->update($data);

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'komponen.ubah',
                objekTipe: 'indikator_komponen',
                objekId: $komponen->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: $komponen->fresh()->toArray(),
                alasan: $alasan,
                dasarIzin: $decision,
            );
        });

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil diperbarui.');
    }

    public function destroy(DestroyIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        abort_if($komponen->indikator_id !== $indikator->id, 404);

        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:delete');
        abort_unless($decision['allowed'], 403, 'Akses penghapusan komponen ditolak.');

        $alasan = $request->validated('alasan');

        DB::transaction(function () use ($komponen, $actor, $alasan, $decision) {
            $nilaiLama = $komponen->toArray();
            $komponen->delete();

            $this->auditLogger->catat(
                actor: $actor,
                tindakan: 'komponen.hapus',
                objekTipe: 'indikator_komponen',
                objekId: $komponen->id,
                nilaiLama: $nilaiLama,
                nilaiBaru: null,
                alasan: $alasan,
                dasarIzin: $decision,
            );
        });

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil dihapus.');
    }
}
