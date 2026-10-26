<?php

namespace App\Actions\Pengukuran;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PengukuranKinerja;
use App\Models\PenugasanIndikator;
use App\Models\PeriodeJadwal;
use App\Models\Unit;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use App\Services\Perencanaan\IndikatorArsipGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EnsureDraftPengukuran
{
    public function __construct(private PermissionResolver $resolver, private PengukuranKinerjaPolicy $policy,
        private IndikatorArsipGuard $arsipGuard, private PengukuranMutationSteps $steps) {}

    /**
     * Bentuk satu header draf untuk kewajiban snapshot × periode, atau kembalikan header indikator × tahun × periode yang sudah ada.
     *
     * Urutan lock: aktor → role aktif (shared) → indikator (exclusive) → calon header → unit → jadwal → snapshot → periode jadwal (shared).
     * Aktor dan indikator diambil sebelum konteks jadwal, sama seperti mutasi pengukuran dan aktivasi jadwal, sehingga tidak ada
     * antrean terbalik; indikator eksklusif menyerialkan pembentukan dengan pergantian PJ dan arsip. Header yang muncul selama
     * menunggu menjadikan permintaan replay: cukup izin baca pada unit header yang dijepit, tanpa guard pembentukan.
     * Unique indikator × tahun × periode tetap pengaman akhir.
     */
    public function handle(User $actor, string $snapshotId, string $periodeId): PengukuranKinerja
    {
        $decision = null;
        // Hanya cabang pembentukan yang mengisi konteks; penolakan replay tidak dicatat sebagai buat_ditolak.
        $context = null;
        try {
            return DB::transaction(function () use ($actor, $snapshotId, $periodeId, &$decision, &$context) {
                // Konteks tak dikenal berakhir 404 sebelum lock dan tanpa audit.
                $requested = JadwalSnapshot::with('jadwal')->findOrFail($snapshotId);
                PeriodeJadwal::where('jadwal_id', $requested->jadwal_id)->where('periode_id', $periodeId)->firstOrFail();
                $actor = User::lockForUpdate()->findOrFail($actor->id);
                $actor->lockActiveRoles();
                $indicator = IndikatorKinerja::lockForUpdate()->findOrFail($requested->indikator_id);
                $existing = PengukuranKinerja::where('indikator_id', $indicator->id)->where('tahun', $requested->jadwal->tahun)
                    ->where('periode_id', $periodeId)->lockForUpdate()->first();
                if ($existing) {
                    if (! $this->resolver->allows($actor, 'pengukuran:read', $existing->targetUnitId())) {
                        throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                    }

                    return $existing;
                }

                $context = ['jadwal_snapshot_id' => $requested->id, 'periode_id' => strtolower($periodeId), 'indikator_id' => $indicator->id, 'tahun' => $requested->jadwal->tahun];
                $unit = Unit::whereKey($requested->unit_id)->sharedLock()->first();
                $schedule = JadwalTahunan::whereKey($requested->jadwal_id)->sharedLock()->firstOrFail();
                $snapshot = JadwalSnapshot::whereKey($requested->id)->sharedLock()->firstOrFail()->setRelation('jadwal', $schedule)->setRelation('unit', $unit);
                $period = PeriodeJadwal::with('periode')->where('jadwal_id', $snapshot->jadwal_id)->where('periode_id', $periodeId)->sharedLock()->firstOrFail();
                $decision = $this->resolver->decide($actor, 'pengukuran:create', $snapshot->unit_id);
                if (! $decision['allowed']) {
                    throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
                }
                $this->arsipGuard->pastikanDapatDibuatkan($indicator, 'pengukuran');
                // Versi terpilih: nomor_versi terbesar di antara snapshot yang sudah efektif pada urutan periode ini.
                $effectiveId = JadwalSnapshot::join('periode', 'periode.id', '=', 'jadwal_snapshot.periode_mulai_id')
                    ->where('jadwal_snapshot.jadwal_id', $snapshot->jadwal_id)->where('jadwal_snapshot.indikator_id', $indicator->id)
                    ->where('periode.urutan', '<=', $period->periode->urutan)->orderByDesc('jadwal_snapshot.nomor_versi')->value('jadwal_snapshot.id');
                if ($effectiveId === null) {
                    throw ValidationException::withMessages(['periode_id' => 'Periode belum termasuk kewajiban efektif indikator pada jadwal ini.']);
                }
                if ($effectiveId !== $snapshot->id) {
                    throw ValidationException::withMessages(['jadwal_snapshot_id' => 'Konteks indikator telah berubah. Muat ulang daftar pengukuran.']);
                }
                $pic = PenugasanIndikator::effectiveOn(today(config('app.business_timezone'))->toDateString())->where('indikator_id', $indicator->id)->value('user_id');
                $errors = $this->policy->creationErrors($actor, $snapshot, $period, $decision, $pic);
                if ($snapshot->jadwal->renstra_id !== $indicator->sasaranStrategis?->renstra_id) {
                    $errors[] = 'Renstra jadwal tidak cocok dengan indikator.';
                }
                if ($errors) {
                    throw ValidationException::withMessages(['pengukuran' => $errors]);
                }

                $created = PengukuranKinerja::create(['indikator_id' => $indicator->id, 'tahun' => $snapshot->jadwal->tahun, 'periode_id' => $period->periode_id,
                    'jadwal_snapshot_id' => $snapshot->id, 'sumber_nilai' => $snapshot->tipe_perhitungan === 'manual' ? 'manual' : 'komponen',
                    'status_perhitungan' => 'belum_diisi', 'nilai' => null, 'status_alur' => 'draft', 'versi' => 1, 'created_by' => $actor->id]);
                $this->steps->writeAudit($actor, $created->id, 'pengukuran.buat', 'Membuat draf pengukuran.', $decision, null,
                    $created->only(['indikator_id', 'tahun', 'periode_id', 'jadwal_snapshot_id', 'sumber_nilai', 'status_perhitungan', 'status_alur', 'versi']));

                return $created;
            });
        } catch (Throwable $exception) {
            if ($context !== null && ($exception instanceof AuthorizationException || $exception instanceof ValidationException)) {
                // Header belum ada: ID percobaan hanya menautkan audit, bukan header tersimpan.
                $this->steps->writeAudit($actor, (string) Str::uuid(), 'pengukuran.buat_ditolak', 'Pembuatan draf pengukuran ditolak.', $decision, null,
                    [...$context, 'jenis_penolakan' => $exception instanceof AuthorizationException ? 'otorisasi' : 'validasi_bisnis',
                        'alasan_penolakan' => $exception instanceof ValidationException ? $exception->errors() : $exception->getMessage()]);
            }
            throw $exception;
        }
    }
}
