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
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:create');

        $data = $request->validated();
        $data['indikator_id'] = $indikator->id;
        $data['created_by'] = $actor->id;

        try {
            DB::transaction(function () use ($indikator, $data, $actor, $decision) {
                // Urutan kunci parent→child, konsisten dengan UpdateIndikator
                // dan ChangeIndicatorFormula yang mengunci Indikator sebelum
                // mutasi: parent dikunci FOR UPDATE dulu agar perubahan tipe
                // konkuren terserialisasi dengan penambahan komponen (tanpa
                // inversi = tanpa deadlock). Guard manual dibaca dari baris
                // terkunci (anti-TOCTOU) dan DIPERTAHANKAN: tambah/ubah
                // langsung saat manual tetap 422; transisi tipe↔komponen hanya
                // via jalur atomik PATCH
                // /perencanaan/indikator/{indikator}/formula.
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedIndikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');

                $komponen = IndikatorKomponen::create(array_merge($data, ['indikator_id' => $lockedIndikator->id]));
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
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:update');

        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        try {
            DB::transaction(function () use ($indikator, $komponen, $data, $actor, $alasan, $decision) {
                // Urutan kunci parent→child seperti store (konsisten dengan
                // UpdateIndikator + ChangeIndicatorFormula): parent FOR UPDATE
                // dulu, baru child FOR UPDATE. Guard manual + kepemilikan
                // dibaca dari baris terkunci (anti-TOCTOU) dan DIPERTAHANKAN;
                // transisi tipe↔komponen hanya via jalur atomik PATCH
                // /perencanaan/indikator/{indikator}/formula.
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedIndikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');

                $lockedKomponen = IndikatorKomponen::whereKey($komponen->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedKomponen->indikator_id !== $lockedIndikator->id, 404);

                $nilaiLama = $this->formatAuditSnapshot($lockedKomponen);
                $lockedKomponen->update($data);
                $nilaiBaru = $this->formatAuditSnapshot($lockedKomponen->fresh());

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.ubah',
                    objekTipe: 'indikator_komponen',
                    objekId: $lockedKomponen->id,
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
        $actor = $request->user()->fresh();
        $decision = $resolver->decide($actor, 'komponen:delete');

        $alasan = $request->validated('alasan');

        try {
            $dirujuk = DB::transaction(function () use ($indikator, $komponen, $actor, $alasan, $decision) {
                // Urutan kunci parent→child seperti store/update: parent
                // FOR UPDATE dulu, baru child FOR UPDATE. Kepemilikan +
                // cek rujukan dibaca dari baris terkunci (anti-TOCTOU).
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                $lockedKomponen = IndikatorKomponen::whereKey($komponen->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedKomponen->indikator_id !== $lockedIndikator->id, 404);

                $isReferenced = DB::table('jadwal_snapshot_komponen')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('rencana_aksi_target')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('pengukuran_komponen')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('klaim_kegiatan')->where('komponen_id', $lockedKomponen->id)->exists();

                if ($isReferenced) {
                    return true;
                }

                $nilaiLama = $this->formatAuditSnapshot($lockedKomponen);
                $lockedKomponen->delete();

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.hapus',
                    objekTipe: 'indikator_komponen',
                    objekId: $lockedKomponen->id,
                    nilaiLama: $nilaiLama,
                    nilaiBaru: null,
                    alasan: $alasan,
                    dasarIzin: $decision,
                );

                return false;
            });
        } catch (QueryException) {
            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena memiliki keterkaitan data pada sistem. Silakan nonaktifkan komponen.');
        }

        if ($dirujuk) {
            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena sudah direferensikan pada data snapshot, pengukuran, rencana aksi, atau klaim kegiatan. Silakan nonaktifkan komponen sebagai alternatif.');
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
