<?php

namespace App\Http\Controllers\Indikator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Indikator\DestroyIndikatorKomponenRequest;
use App\Http\Requests\Indikator\StoreIndikatorKomponenRequest;
use App\Http\Requests\Indikator\UpdateIndikatorKomponenRequest;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Kinerja\IndikatorPerhitunganService;
use App\Services\Kinerja\KomponenMutationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class IndikatorKomponenController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly IndikatorPerhitunganService $perhitunganService,
        private readonly KomponenMutationService $mutasiKomponen,
        private readonly ResolveLockedActor $lockedActor,
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
        $data = $request->validated();
        $data['indikator_id'] = $indikator->id;
        $data['created_by'] = $actor->id;

        try {
            $result = DB::transaction(function () use ($indikator, $data, $actor) {
                $auth = $this->lockedActor->handle($actor, 'komponen:create');
                if (! $auth['aktor'] || $auth['aktor']->status !== 'aktif' || ! $auth['keputusan']->allowed) {
                    return ['denied' => $auth['keputusan']->toAuditBasis()];
                }
                $decision = $auth['keputusan']->toAuditBasis();

                // Aktor/ACL → induk → child; nilai tipe dan komposisi dibaca setelah lock.
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedIndikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');

                $candidate = $lockedIndikator->komponen()->orderBy('id')->lockForUpdate()->get();
                $candidate->push($this->mutasiKomponen->modelKandidat($lockedIndikator->id, $data));
                $this->mutasiKomponen->pastikanDefinisiValid($lockedIndikator, $candidate);
                $komponen = $this->mutasiKomponen->buat($lockedIndikator->id, $data, $actor->id);
                $this->mutasiKomponen->bumpVersiFormula($lockedIndikator);

                $this->auditLogger->catat(
                    actor: $actor,
                    tindakan: 'komponen.buat',
                    objekTipe: 'indikator_komponen',
                    objekId: $komponen->id,
                    nilaiLama: null,
                    nilaiBaru: $this->mutasiKomponen->formatAuditSnapshot($komponen),
                    alasan: 'Penambahan komponen indikator '.$komponen->kode.' ('.$komponen->label.')',
                    dasarIzin: $decision,
                );

                return ['denied' => null];
            });
        } catch (QueryException $e) {
            if ($this->mutasiKomponen->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'kode' => $this->mutasiKomponen->pesanKodeDuplikat(),
                ]);
            }

            throw $e;
        }

        $this->auditDenial($actor, $result['denied'], 'komponen.buat_ditolak', (string) Str::uuid(), $request->input('alasan'));

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil ditambahkan.');
    }

    public function update(UpdateIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $data = $request->validated();
        $alasan = $data['alasan'];
        unset($data['alasan']);

        try {
            $result = DB::transaction(function () use ($indikator, $komponen, $data, $actor, $alasan) {
                $auth = $this->lockedActor->handle($actor, 'komponen:update');
                if (! $auth['aktor'] || $auth['aktor']->status !== 'aktif' || ! $auth['keputusan']->allowed) {
                    return ['denied' => $auth['keputusan']->toAuditBasis()];
                }
                $decision = $auth['keputusan']->toAuditBasis();

                // Semua mutation mengunci induk sebelum child untuk menjaga formula utuh.
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                abort_if($lockedIndikator->tipe_perhitungan === 'manual', 422, 'Indikator bertipe manual tidak menggunakan komponen perhitungan.');

                $children = $lockedIndikator->komponen()->orderBy('id')->lockForUpdate()->get();
                $lockedKomponen = $children->firstWhere('id', $komponen->getKey());
                abort_unless($lockedKomponen, 404);
                abort_if($lockedKomponen->indikator_id !== $lockedIndikator->id, 404);

                $nilaiLama = $this->mutasiKomponen->formatAuditSnapshot($lockedKomponen);
                $candidate = $children->map(function (IndikatorKomponen $item) use ($lockedKomponen, $data) {
                    $copy = clone $item;
                    if ($item->id === $lockedKomponen->id) {
                        $copy->fill($this->mutasiKomponen->normalisasiInput($data));
                    }

                    return $copy;
                });
                $this->mutasiKomponen->pastikanDefinisiValid($lockedIndikator, $candidate);
                $lockedKomponen->update($this->mutasiKomponen->normalisasiInput($data));
                $this->mutasiKomponen->bumpVersiFormula($lockedIndikator);
                $freshKomponen = $lockedKomponen->fresh();
                $nilaiBaru = $this->mutasiKomponen->formatAuditSnapshot($freshKomponen ?? $lockedKomponen);

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

                return ['denied' => null];
            });
        } catch (QueryException $e) {
            if ($this->mutasiKomponen->isUniqueConstraintViolation($e)) {
                throw ValidationException::withMessages([
                    'kode' => $this->mutasiKomponen->pesanKodeDuplikat(),
                ]);
            }

            throw $e;
        }

        $this->auditDenial($actor, $result['denied'], 'komponen.ubah_ditolak', $komponen->id, $alasan);

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil diperbarui.');
    }

    public function destroy(DestroyIndikatorKomponenRequest $request, IndikatorKinerja $indikator, IndikatorKomponen $komponen, PermissionResolver $resolver): RedirectResponse
    {
        $actor = $request->user()->fresh();
        $alasan = $request->validated('alasan');

        try {
            $result = DB::transaction(function () use ($indikator, $komponen, $actor, $alasan) {
                $auth = $this->lockedActor->handle($actor, 'komponen:delete');
                if (! $auth['aktor'] || $auth['aktor']->status !== 'aktif' || ! $auth['keputusan']->allowed) {
                    return ['denied' => $auth['keputusan']->toAuditBasis(), 'referenced' => false];
                }
                $decision = $auth['keputusan']->toAuditBasis();
                // Pemeriksaan dependensi tetap mendahului kandidat penghapusan.
                $lockedIndikator = IndikatorKinerja::whereKey($indikator->getKey())->lockForUpdate()->firstOrFail();
                $children = $lockedIndikator->komponen()->orderBy('id')->lockForUpdate()->get();
                $lockedKomponen = $children->firstWhere('id', $komponen->getKey());
                abort_unless($lockedKomponen, 404);
                abort_if($lockedKomponen->indikator_id !== $lockedIndikator->id, 404);

                $isReferenced = DB::table('jadwal_snapshot_komponen')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('rencana_aksi_target')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('pengukuran_komponen')->where('komponen_id', $lockedKomponen->id)->exists()
                    || DB::table('klaim_kegiatan')->where('komponen_id', $lockedKomponen->id)->exists();

                if ($isReferenced) {
                    return ['denied' => null, 'referenced' => true];
                }

                $this->mutasiKomponen->pastikanDefinisiValid($lockedIndikator, $children->reject(fn ($item) => $item->id === $lockedKomponen->id)->values());
                $nilaiLama = $this->mutasiKomponen->formatAuditSnapshot($lockedKomponen);
                $lockedKomponen->delete();
                $this->mutasiKomponen->bumpVersiFormula($lockedIndikator);

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

                return ['denied' => null, 'referenced' => false];
            });
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23503') {
                throw $e;
            }

            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena memiliki keterkaitan data pada sistem. Silakan nonaktifkan komponen.');
        }

        $this->auditDenial($actor, $result['denied'], 'komponen.hapus_ditolak', $komponen->id, $alasan);
        if ($result['referenced']) {
            return redirect()->to("/indikator/{$indikator->id}/komponen")
                ->with('error', 'Komponen tidak dapat dihapus karena sudah direferensikan pada data snapshot, pengukuran, rencana aksi, atau klaim kegiatan. Silakan nonaktifkan komponen sebagai alternatif.');
        }

        return redirect()->to("/indikator/{$indikator->id}/komponen")
            ->with('success', 'Komponen indikator berhasil dihapus.');
    }

    /**
     * Penolakan dicatat setelah transaksi mutation berakhir agar audit tidak ikut rollback.
     * Alasan mentah dilindungi oleh boundary AuditLogger.
     *
     * @param  array<string, mixed>|null  $basis
     */
    private function auditDenial(User $actor, ?array $basis, string $event, string $objectId, mixed $reason): void
    {
        if ($basis === null) {
            return;
        }
        $this->auditLogger->catat(
            actor: $actor,
            tindakan: $event,
            objekTipe: 'indikator_komponen',
            objekId: $objectId,
            alasan: is_string($reason) ? $reason : 'Mutation komponen ditolak karena wewenang tidak lagi berlaku saat transaksi.',
            dasarIzin: $basis,
        );
        abort(403, 'Anda tidak berwenang mengubah komponen indikator.');
    }
}
