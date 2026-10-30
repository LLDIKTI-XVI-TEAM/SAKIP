<?php

namespace App\Actions\Jadwal;

use App\Http\Requests\Jadwal\KoreksiSnapshotRequest;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\TargetKinerja;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Jadwal\JadwalActivationReadiness;
use App\Services\Jadwal\JadwalSnapshotWriter;
use App\Services\Jadwal\SelisihSnapshotMaster;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Support\AuditReason;
use App\Support\PermissionDecision;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KoreksiSnapshot
{
    private const PESAN_JADWAL = 'Snapshot hanya dapat dikoreksi pada jadwal aktif yang belum melewati tanggal penutupan.';

    private const PESAN_USANG = 'Snapshot sudah dikoreksi atau berubah. Muat ulang halaman snapshot sebelum mengoreksi.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit,
        private readonly JadwalActivationReadiness $readiness, private readonly KonteksBekuRencanaAksi $konteks,
        private readonly SelisihSnapshotMaster $selisih, private readonly JadwalSnapshotWriter $snapshots) {}

    /**
     * Menerbitkan versi pengganti snapshot dari data master terkini (Q35 §35.1, Q79). Versi lama, rujukan pengukuran,
     * dan versi Rencana Aksi tidak disentuh; jadwal tetap `aktif`. Izin dinilai per kelompok kolom yang berbeda.
     * Urutan lock: aktor → konfigurasi periode shared → indikator → jadwal shared → snapshot terbaru → target → komponen aktif,
     * sama dengan TambahIndikatorJadwal; simpan target Rencana Aksi juga mengunci indikator sebelum jadwal dan snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, string $jadwalId, string $snapshotId, array $data): string
    {
        // UUID huruf kapital di URL/body dinormalisasi agar pencocokan dan audit memakai bentuk yang tersimpan.
        $jadwalId = strtolower($jadwalId);
        $snapshotId = strtolower($snapshotId);
        /** @var array<string, array<string, mixed>> $basis */
        $basis = [];
        try {
            return DB::transaction(function () use ($actor, $jadwalId, $snapshotId, $data, &$basis): string {
                /** @var array<string, PermissionDecision> $keputusan */
                $keputusan = [];
                foreach (SelisihSnapshotMaster::IZIN as $kode) {
                    $keputusan[$kode] = $this->lockedActor->handle($actor, $kode)['keputusan'];
                }
                if (! collect($keputusan)->contains(fn (PermissionDecision $decision): bool => $decision->allowed)) {
                    $basis = array_map(fn (PermissionDecision $decision): array => $decision->toAuditBasis(), $keputusan);
                    throw new AuthorizationException('Anda tidak memiliki izin mengoreksi snapshot.');
                }
                if (array_diff(array_keys($data), array_keys(KoreksiSnapshotRequest::inputRules())) !== []) {
                    throw ValidationException::withMessages(['snapshot' => 'Permintaan memuat field yang tidak didukung.']);
                }
                $data = Validator::make($data, KoreksiSnapshotRequest::inputRules(), KoreksiSnapshotRequest::inputMessages())->validate();

                Periode::lockConfiguration();
                $diminta = JadwalSnapshot::whereKey($snapshotId)->first();
                if ($diminta === null || $diminta->jadwal_id !== $jadwalId) {
                    throw ValidationException::withMessages(['snapshot' => 'Snapshot tidak ditemukan pada jadwal ini.']);
                }
                IndikatorKinerja::whereKey($diminta->indikator_id)->lockForUpdate()->first();
                $jadwal = JadwalTahunan::whereKey($jadwalId)->sharedLock()->firstOrFail();
                if ($jadwal->status !== 'aktif' || $jadwal->tahunDitutup()) {
                    throw ValidationException::withMessages(['jadwal' => self::PESAN_JADWAL]);
                }
                $terbaru = $this->konteks->snapshotTerbaru($jadwalId, $diminta->indikator_id, kunci: true);
                if ($terbaru?->id !== $diminta->id || strtolower($data['expected_snapshot_id']) !== $terbaru->id
                    || (int) $data['expected_snapshot_versi'] !== $terbaru->nomor_versi) {
                    throw ValidationException::withMessages(['expected_snapshot_id' => self::PESAN_USANG])->status(409);
                }
                TargetKinerja::where('indikator_kinerja_id', $terbaru->indikator_id)->where('tahun', $jadwal->tahun)->orderBy('id')->lockForUpdate()->get(['id']);
                IndikatorKomponen::where('indikator_id', $terbaru->indikator_id)->where('aktif', true)->orderBy('id')->lockForUpdate()->get(['id']);

                // Syarat salinan sama dengan pembentukan versi 1, agar versi koreksi tidak membekukan data yang ditolak aktivasi.
                $context = $this->readiness->context($jadwal);
                $tolak = fn (string $pesan): ValidationException => ValidationException::withMessages(['snapshot' => $pesan]);
                $indikator = $context['eligible']->first(fn (IndikatorKinerja $row): bool => $row->id === $terbaru->indikator_id)
                    ?? throw $tolak('Indikator tidak aktif, belum berlaku pada tahun jadwal, atau bukan bagian Renstra jadwal ini.');
                if (! $this->readiness->dapatDibekukan($indikator)) {
                    throw $tolak('Data indikator tidak dapat dibekukan (nama terlalu panjang atau komponen tidak sesuai tipe perhitungan). Perbaiki pada modul indikator.');
                }
                $target = $context['targets']->get($indikator->id);
                if ($target === null || $target->target_tahunan === null) {
                    throw $tolak('Target tahunan indikator pada Perjanjian Kinerja belum diisi.');
                }
                if (Unit::whereKey($indikator->unit_id)->sharedLock()->value('status') !== 'aktif') {
                    throw $tolak('Unit penanggung jawab indikator tidak aktif. Perbaiki pada modul indikator.');
                }
                $periode = fn (string $id): ?PeriodeJadwal => $jadwal->periode->first(fn (PeriodeJadwal $window): bool => $window->periode_id === strtolower($id));
                $mulai = $periode($data['periode_mulai_id'])
                    ?? throw ValidationException::withMessages(['periode_mulai_id' => 'Periode mulai berlaku harus salah satu periode jadwal ini.']);
                $mulaiLama = $periode($terbaru->periode_mulai_id)?->periode ?? Periode::findOrFail($terbaru->periode_mulai_id);
                if ($mulai->periode->urutan < $mulaiLama->urutan) {
                    throw ValidationException::withMessages(['periode_mulai_id' => 'Periode mulai berlaku tidak boleh lebih awal dari periode mulai versi sebelumnya.']);
                }

                $selisih = $this->selisih->hitung($terbaru, $indikator, $target);
                if ($selisih['izin'] === []) {
                    throw $tolak('Data master sama dengan versi snapshot terbaru. Perbaiki data master terlebih dahulu.');
                }
                // Hanya kelompok kolom yang berbeda yang menjadi dasar izin; deny pada salah satunya menolak seluruh koreksi.
                $basis = [];
                foreach ($selisih['izin'] as $kode) {
                    $basis[$kode] = $keputusan[$kode]->toAuditBasis();
                }
                if (collect($selisih['izin'])->contains(fn (string $kode): bool => ! $keputusan[$kode]->allowed)) {
                    throw new AuthorizationException('Anda tidak memiliki izin untuk seluruh kolom yang dikoreksi.');
                }

                return $this->snapshots->terbitkanKoreksi($actor, $terbaru, $indikator, $target, $mulai->periode_id,
                    AuditReason::sanitize($data['alasan_koreksi']), AuditReason::sanitize($data['rujukan_koreksi']), $basis);
            });
        } catch (AuthorizationException|ValidationException|QueryException|DeadlockException $exception) {
            // Deadlock/serialization/lock timeout menjadi konflik aman dan unique versi ganda menjadi token usang;
            // QueryException lain tetap error internal. Laravel membungkus deadlock transaksi bertingkat sebagai DeadlockException.
            if ($exception instanceof QueryException || $exception instanceof DeadlockException) {
                $kode = $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null;
                if ($exception instanceof QueryException && ! in_array($kode, ['40P01', '40001', '55P03', '23505'], true)) {
                    throw $exception;
                }
                $exception = $kode === '23505'
                    ? ValidationException::withMessages(['expected_snapshot_id' => self::PESAN_USANG])->status(409)
                    : ValidationException::withMessages(['jadwal' => 'Data sedang diubah pengguna lain. Muat ulang halaman snapshot lalu coba lagi.']);
            }
            // Di luar rollback, satu kali per percobaan, memakai keputusan izin yang benar-benar dipakai.
            $this->audit->catat(actor: $actor, tindakan: 'jadwal_snapshot.koreksi_ditolak', objekTipe: 'jadwal_snapshot',
                objekId: Str::isUuid($snapshotId) ? $snapshotId : (string) Str::uuid(),
                nilaiBaru: ['jadwal_id' => $jadwalId, 'alasan_penolakan' => $exception instanceof AuthorizationException ? 'izin_ditolak' : 'aturan_koreksi'],
                dasarIzin: $basis !== [] ? $basis : null);
            throw $exception;
        }
    }
}
