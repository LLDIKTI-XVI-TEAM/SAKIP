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
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
                'create' => $indikator->tipe_perhitungan !== 'manual' && $resolver->allows($actor, 'komponen:create'),
                'update' => $indikator->tipe_perhitungan !== 'manual' && $resolver->allows($actor, 'komponen:update'),
                'delete' => $resolver->allows($actor, 'komponen:delete'),
            ],
        ]);
    }

    public function store(StoreIndikatorKomponenRequest $request, IndikatorKinerja $indikator, PermissionResolver $resolver): RedirectResponse
    {
        abort_if($indikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');

        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:create');

        $data = $request->validated();
        $data['indikator_id'] = $indikator->id;
        $data['created_by'] = $actor->id;

        try {
            DB::transaction(function () use ($data, $actor, $decision) {
                $komponen = IndikatorKomponen::create($data);
                $komponen = $komponen->fresh();

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.buat',
                    objekTipe: 'indikator_komponen',
                    objekId: $komponen->id,
                    nilaiLama: null,
                    nilaiBaru: $this->formatAuditSnapshot($komponen),
                    alasan: 'Penambahan komponen indikator '.$komponen->kode.' ('.$komponen->label.')',
                    dasarIzin: $decision,
                );
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'kode' => 'Kode komponen sudah digunakan pada indikator ini.',
                ]);
            }

            throw $e;
        }

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil ditambahkan.');
    }

    public function update(UpdateIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        abort_if($indikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');
        abort_if($komponen->indikator_id !== $indikator->id, 404);

        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:update');

        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        try {
            DB::transaction(function () use ($komponen, $data, $actor, $alasan, $decision) {
                $nilaiLama = $this->formatAuditSnapshot($komponen);
                $komponen->update($data);
                $nilaiBaru = $this->formatAuditSnapshot($komponen->fresh());

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.ubah',
                    objekTipe: 'indikator_komponen',
                    objekId: $komponen->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: $nilaiBaru,
                    alasan: $alasan,
                    dasarIzin: $decision,
                );
            });
        } catch (QueryException $e) {
            if ($this->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'kode' => 'Kode komponen sudah digunakan pada indikator ini.',
                ]);
            }

            throw $e;
        }

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil diperbarui.');
    }

    public function destroy(DestroyIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        abort_if($komponen->indikator_id !== $indikator->id, 404);

        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:delete');

        $alasan = $request->validated('alasan');

        $isReferenced = DB::table('jadwal_snapshot_komponen')->where('komponen_id', $komponen->id)->exists()
            || DB::table('rencana_aksi_target')->where('komponen_id', $komponen->id)->exists()
            || DB::table('pengukuran_komponen')->where('komponen_id', $komponen->id)->exists()
            || DB::table('klaim_kegiatan')->where('komponen_id', $komponen->id)->exists();

        if ($isReferenced) {
            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena sudah direferensikan pada data snapshot, pengukuran, rencana aksi, atau klaim kegiatan. Silakan nonaktifkan komponen sebagai alternatif.');
        }

        try {
            DB::transaction(function () use ($komponen, $actor, $alasan, $decision) {
                $nilaiLama = $this->formatAuditSnapshot($komponen);
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
        } catch (QueryException) {
            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena memiliki keterkaitan data pada sistem. Silakan nonaktifkan komponen.');
        }

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil dihapus.');
    }

    /**
     * Membentuk snapshot audit dengan memastikan nilai desimal bobot tetap eksak sebagai string.
     *
     * @return array<string, mixed>
     */
    private function formatAuditSnapshot(IndikatorKomponen $komponen): array
    {
        $snapshot = $komponen->toArray();
        $rawBobot = $komponen->getRawOriginal('bobot');
        if ($rawBobot !== null && $rawBobot !== '') {
            $snapshot['bobot'] = (string) $rawBobot;
        } elseif (isset($snapshot['bobot'])) {
            $snapshot['bobot'] = (string) $snapshot['bobot'];
        }

        return $snapshot;
    }

    /**
     * Mengecek apakah QueryException merupakan pelanggaran unique constraint pada kode komponen.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $errorCode = $e->errorInfo[1] ?? null;
        $message = strtolower($e->getMessage());

        return $sqlState === '23505'
            || $errorCode === 1062
            || $errorCode === 19
            || str_contains($message, 'unique')
            || str_contains($message, 'duplicate');
    }
}
