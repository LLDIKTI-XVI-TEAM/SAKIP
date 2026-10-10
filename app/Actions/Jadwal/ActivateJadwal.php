<?php

namespace App\Actions\Jadwal;

use App\Http\Requests\Jadwal\ActivateJadwalRequest;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\Pengaturan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\SasaranStrategis;
use App\Models\TargetKinerja;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Jadwal\JadwalActivationReadiness;
use App\Support\AuditReason;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivateJadwal
{
    private const STALE = 'Jadwal sudah berubah. Muat ulang detail jadwal sebelum mengaktifkan.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit, private readonly JadwalActivationReadiness $readiness) {}

    /**
     * Aktivasi pertama draft → aktif; snapshot baru, transisi, penanda pengecualian, dan audit atomik.
     * Urutan lock (spec §5) mengikuti writer existing: konfigurasi periode shared → PK → setting unggahan shared
     * → seluruh indikator Renstra → Sasaran shared → Renstra → Jadwal → target dan komponen.
     *
     * @param  array<string, mixed>  $data
     * @return array{operation_id: string, jadwal_id: string, status: string, activated_at: string, revisi: int, changed: bool, snapshot_created_count: int}
     */
    public function handle(User $actor, string $jadwalId, array $data): array
    {
        /** @var PermissionDecision|null $decision */
        $decision = null;
        try {
            return DB::transaction(function () use ($actor, $jadwalId, $data, &$decision): array {
                $decision = $this->lockedActor->handle($actor, PermissionCodes::JADWAL_AKTIVASI)['keputusan'];
                if (! $decision->allowed) {
                    throw new AuthorizationException('Anda tidak memiliki izin mengaktifkan jadwal.');
                }
                $data = $this->validated($data);
                $jadwalId = strtolower($jadwalId);
                Periode::lockConfiguration();
                // Identitas awal hanya kandidat; kebenarannya dicek ulang setelah Jadwal terkunci.
                $candidate = JadwalTahunan::findOrFail($jadwalId);
                if ($candidate->isActivated()) {
                    return $this->outcome($candidate, $data['operation_id'], changed: false, created: 0);
                }
                $pk = RenstraPk::where('renstra_id', $candidate->renstra_id)->where('tahun', $candidate->tahun)->lockForUpdate()->first();
                // Nilai yang dipakai G4 hanya hasil baca terkunci ini: row absent tidak terkunci, sehingga nilai yang muncul
                // belakangan tidak boleh mengaktifkan pengecualian (absent tetap default canonical true).
                $unggahanAktif = Pengaturan::where('kunci', 'berkas.unggahan_aktif')->sharedLock()->value('nilai');
                $indicatorIds = $this->indicatorIds($candidate->renstra_id, lock: true);
                SasaranStrategis::where('renstra_id', $candidate->renstra_id)->orderBy('id')->sharedLock()->get(['id']);
                Renstra::whereKey($candidate->renstra_id)->lockForUpdate()->firstOrFail();
                // Insert indikator yang selesai sebelum Renstra terkunci terdeteksi di sini; ID baru tidak dikunci sambil memegang Renstra.
                if ($this->indicatorIds($candidate->renstra_id) !== $indicatorIds) {
                    throw ValidationException::withMessages(['aktivasi' => 'Daftar indikator berubah saat aktivasi. Muat ulang detail jadwal lalu coba lagi.']);
                }
                $jadwal = JadwalTahunan::whereKey($jadwalId)->lockForUpdate()->firstOrFail();
                // Replay diperiksa sebelum expected_revisi agar payload lama dari aktivasi yang sudah berhasil tetap no-op.
                if ($jadwal->isActivated()) {
                    return $this->outcome($jadwal, $data['operation_id'], changed: false, created: 0);
                }
                if ($jadwal->renstra_id !== $candidate->renstra_id || $jadwal->tahun !== $candidate->tahun || $jadwal->revisi !== (int) $data['expected_revisi']) {
                    throw ValidationException::withMessages(['aktivasi' => self::STALE]);
                }
                TargetKinerja::whereIn('indikator_kinerja_id', $indicatorIds)->where('tahun', $jadwal->tahun)->orderBy('id')->lockForUpdate()->get(['id']);
                IndikatorKomponen::whereIn('indikator_id', $indicatorIds)->where('aktif', true)->orderBy('id')->lockForUpdate()->get(['id']);

                // Satu waktu server sesudah seluruh lock; dipotong ke detik karena activated_at timestamp(0) membulatkan pecahan detik.
                $at = CarbonImmutable::now()->startOfSecond();
                $context = [...$this->readiness->context($jadwal), 'unggahan_aktif' => $unggahanAktif];
                if ($context['pk']?->id !== $pk?->id) {
                    throw ValidationException::withMessages(['aktivasi' => self::STALE]);
                }
                $result = $this->readiness->evaluate($context, $at);
                if (! $result['allowed']) {
                    throw ValidationException::withMessages(['aktivasi' => [
                        ...array_column($result['blockers'], 'message'),
                        ...array_column(array_filter($result['gates'], fn (array $gate): bool => $gate['status'] === 'gagal'), 'message'),
                    ]]);
                }

                $basis = $decision->toAuditBasis();
                $snapshotIds = $this->createSnapshots($actor, $context, $data['alasan'], $basis);
                // Snapshot existing milik jadwal ini yang belum final ikut dibekukan. Flag false hanya
                // mungkin berasal dari jalur publikasi sebelum finalisasi ada, dan membiarkannya terbuka
                // membuat komposisi terbit masih dapat disisipi komponen. Draf koreksi berversi yang belum
                // terbit (bila kelak ada) wajib dikecualikan di sini dan di trigger
                // `finalisasi_snapshot_saat_jadwal_aktif`.
                $finalizedExisting = JadwalSnapshot::where('jadwal_id', $jadwal->id)
                    ->where('komposisi_final', false)
                    ->update(['komposisi_final' => true]);
                $exception = $this->readiness->usesAttachmentException($context);
                if ($exception) {
                    $this->markAttachmentException($actor, $context['pk'], $data['alasan'], $basis);
                }
                $before = $jadwal->only(['status', 'revisi', 'renstra_pk_id', 'activated_at']);
                $jadwal->forceFill(['status' => 'aktif', 'renstra_pk_id' => $context['pk']->id, 'activated_at' => $at->format('Y-m-d H:i:s'), 'revisi' => $jadwal->revisi + 1])->save();
                $this->audit->catat(actor: $actor, tindakan: 'jadwal.aktivasi', objekTipe: 'jadwal', objekId: $jadwal->id, nilaiLama: $before,
                    nilaiBaru: [...$jadwal->only(['status', 'revisi', 'renstra_pk_id']), 'activated_at' => $at->toIso8601ZuluString(),
                        'snapshot_ids' => $snapshotIds, 'snapshot_created_count' => count($snapshotIds),
                        'snapshot_finalized_existing_count' => $finalizedExisting, 'pengecualian_lampiran_pk' => $exception],
                    alasan: $data['alasan'], dasarIzin: $basis);

                return $this->outcome($jadwal, $data['operation_id'], changed: true, created: count($snapshotIds));
            });
        } catch (AuthorizationException|ValidationException|QueryException|DeadlockException $exception) {
            // Deadlock/serialization/lock timeout menjadi konflik aman (spec §5); QueryException lain tetap error internal.
            // Di transaksi bertingkat Laravel membungkus deadlock sebagai DeadlockException.
            if ($exception instanceof QueryException || $exception instanceof DeadlockException) {
                if ($exception instanceof QueryException && ! in_array($exception->errorInfo[0] ?? null, ['40P01', '40001', '55P03'], true)) {
                    throw $exception;
                }
                $exception = ValidationException::withMessages(['aktivasi' => 'Data sedang diubah pengguna lain. Muat ulang detail jadwal lalu coba lagi.']);
            }
            // Di luar rollback, satu kali per percobaan, memakai keputusan izin yang benar-benar menolak.
            $this->audit->catat(actor: $actor, tindakan: 'jadwal.aktivasi_ditolak', objekTipe: 'jadwal', objekId: strtolower($jadwalId),
                nilaiBaru: ['alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_aktivasi'], dasarIzin: $decision?->toAuditBasis());
            throw $exception;
        }
    }

    /** @param array<string, mixed> $data @return array{expected_revisi: int, operation_id: string, alasan: string} */
    private function validated(array $data): array
    {
        $rules = ActivateJadwalRequest::inputRules();
        if (array_diff(array_keys($data), array_keys($rules)) !== []) {
            throw ValidationException::withMessages(['jadwal' => 'Permintaan memuat field yang tidak didukung.']);
        }
        $data['alasan'] = is_string($data['alasan'] ?? null) ? trim($data['alasan']) : ($data['alasan'] ?? null);
        $request = new ActivateJadwalRequest;
        $data = Validator::make($data, $rules, $request->messages(), $request->attributes())->validate();

        return ['expected_revisi' => (int) $data['expected_revisi'], 'operation_id' => strtolower($data['operation_id']), 'alasan' => AuditReason::sanitize($data['alasan'])];
    }

    /** Seluruh indikator Renstra termasuk arsip/tahun mendatang, agar perubahan status tidak lolos dari lock. @return list<string> */
    private function indicatorIds(string $renstraId, bool $lock = false): array
    {
        return IndikatorKinerja::whereIn('sasaran_strategis_id', SasaranStrategis::select('id')->where('renstra_id', $renstraId))
            ->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->pluck('id')->all();
    }

    /**
     * Hanya pasangan tanpa snapshot versi mana pun; nilai decimal disalin sebagai string mentah numeric(30,12).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $basis
     * @return list<string>
     */
    private function createSnapshots(User $actor, array $context, string $alasan, array $basis): array
    {
        // Periode pilihan dengan urutan terendah, bukan periode berjalan; periode lampau ikut dibekukan.
        $periodeMulai = $context['jadwal']->periode->sortBy(fn (PeriodeJadwal $window): int => $window->periode->urutan)->first()->periode_id;
        $ids = [];
        foreach ($context['baru'] as $indikator) {
            /** @var IndikatorKinerja $indikator */
            $target = $context['targets']->get($indikator->id);
            $snapshot = JadwalSnapshot::create([
                'jadwal_id' => $context['jadwal']->id, 'indikator_id' => $indikator->id, 'nomor_versi' => 1, 'periode_mulai_id' => $periodeMulai,
                'unit_id' => $indikator->unit_id, 'nama' => $indikator->nama, 'definisi' => $indikator->definisi_operasional, 'satuan' => $indikator->satuan,
                'presisi' => $indikator->presisi, 'desimal_tampilan' => $indikator->desimal_tampilan, 'arah' => $indikator->arah,
                'tipe_perhitungan' => $indikator->tipe_perhitungan, 'target' => $target->getRawOriginal('target_tahunan'), 'baseline' => $target->getRawOriginal('baseline'),
            ]);
            $komponen = $indikator->komponen->map(fn (IndikatorKomponen $row): array => [
                'komponen_id' => $row->id, 'kode' => $row->kode, 'label' => $row->label, 'peran' => $row->peran, 'bobot' => $row->getRawOriginal('bobot'), 'urutan' => $row->urutan,
            ])->all();
            // Satu INSERT per snapshot agar lock aktivasi tidak tertahan oleh round-trip per komponen.
            JadwalSnapshotKomponen::insert(array_map(fn (array $row): array => ['id' => (string) Str::uuid(), 'jadwal_snapshot_id' => $snapshot->id, ...$row], $komponen));
            // Finalisasi komposisi: komposisi beku sejak terbit. Setelah flag ini true,
            // guard INSERT menolak child tambahan sehingga rumus yang dilihat pembaca RA tidak dapat
            // berubah tanpa versi baru. Hanya kolom flag yang berubah, jadi guard UPDATE (yang
            // membandingkan seluruh kolom beku lain) meloloskannya.
            JadwalSnapshot::whereKey($snapshot->id)->update(['komposisi_final' => true]);
            $this->audit->catat(actor: $actor, tindakan: 'jadwal_snapshot.buat', objekTipe: 'jadwal_snapshot', objekId: $snapshot->id,
                nilaiBaru: [...$snapshot->only(['jadwal_id', 'indikator_id', 'nomor_versi', 'periode_mulai_id', 'unit_id', 'nama', 'satuan', 'presisi', 'desimal_tampilan', 'arah', 'tipe_perhitungan']),
                    'komposisi_final' => true,
                    'target' => $snapshot->getRawOriginal('target'), 'baseline' => $snapshot->getRawOriginal('baseline'),
                    'komponen' => array_map(fn (array $row): array => array_diff_key($row, ['label' => true]), $komponen)],
                alasan: $alasan, dasarIzin: $basis);
            $ids[] = $snapshot->id;
        }

        return $ids;
    }

    /**
     * Penanda lifecycle existing berkas.tandai_tidak_dapat_dipenuhi untuk PK; tidak diduplikasi bila penanda aktif sudah ada.
     * Pencabutan tetap milik UpdateStoragePolicyAction.
     *
     * @param  array<string, mixed>  $basis
     */
    private function markAttachmentException(User $actor, RenstraPk $pk, string $alasan, array $basis): void
    {
        $markers = DB::table('audit_log as a')->where('a.objek_tipe', 'renstra_pk')->where('a.objek_id', $pk->id)->where('a.tindakan', 'berkas.tandai_tidak_dapat_dipenuhi');
        // Definisi "aktif" sama dengan UpdateStoragePolicyAction: belum ada pencabutan yang merujuk penanda tersebut.
        $active = (clone $markers)->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('audit_log as a2')
            ->whereColumn('a2.objek_id', 'a.objek_id')->whereColumn('a2.objek_tipe', 'a.objek_tipe')->where('a2.tindakan', 'berkas.cabut_tidak_dapat_dipenuhi')
            ->where(fn ($match) => $match->whereRaw("(a2.nilai_lama->>'penanda_audit_id') = a.id::text")->orWhereRaw("(a2.nilai_baru->>'penanda_audit_id') = a.id::text")
                ->orWhere(fn ($legacy) => $legacy->whereNull(DB::raw("a2.nilai_lama->>'penanda_audit_id'"))->whereNull(DB::raw("a2.nilai_baru->>'penanda_audit_id'"))
                    ->whereColumn('a2.waktu', '>', 'a.waktu'))))->exists();
        if ($active) {
            return;
        }
        $this->audit->catat(actor: $actor, tindakan: 'berkas.tandai_tidak_dapat_dipenuhi', objekTipe: 'renstra_pk', objekId: $pk->id,
            nilaiLama: ['nomor_pk' => $pk->nomor_pk, 'tahun' => $pk->tahun, 'status_gerbang' => 'normal'],
            nilaiBaru: ['nomor_pk' => $pk->nomor_pk, 'tahun' => $pk->tahun, 'status_gerbang' => 'tidak_dapat_dipenuhi', 'gerbang' => 'lampiran_pk',
                'sebab' => 'saklar_unggahan_global_nonaktif', 'kunci_setelan' => 'berkas.unggahan_aktif', 'siklus_penandaan' => $markers->count() + 1],
            alasan: $alasan, dasarIzin: $basis);
    }

    /** @return array{operation_id: string, jadwal_id: string, status: string, activated_at: string, revisi: int, changed: bool, snapshot_created_count: int} */
    private function outcome(JadwalTahunan $jadwal, string $operationId, bool $changed, int $created): array
    {
        return ['operation_id' => $operationId, 'jadwal_id' => $jadwal->id, 'status' => 'aktif',
            'activated_at' => CarbonImmutable::parse($jadwal->activated_at)->toIso8601ZuluString(), 'revisi' => $jadwal->revisi,
            'changed' => $changed, 'snapshot_created_count' => $created];
    }
}
