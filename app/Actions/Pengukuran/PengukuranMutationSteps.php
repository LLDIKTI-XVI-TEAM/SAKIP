<?php

namespace App\Actions\Pengukuran;

use App\Actions\Audit\WriteAuditLog;
use App\Models\BuktiDukung;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\JenisBerkas;
use App\Models\PengukuranKinerja;
use App\Models\PengukuranKomponen;
use App\Models\PeriodeJadwal;
use App\Models\RiwayatPengukuran;
use App\Models\User;
use App\Policies\PengukuranKinerjaPolicy;
use App\Services\Authorization\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Langkah bersama lima Action mutasi pengukuran: persiapan state terkunci, penyimpanan input/bukti,
 * dan penyusunan audit/provenance. Kelas ini tidak memilih tindakan dan tidak membuka transaksi;
 * transaksi serta kompensasi tetap dimiliki tiap Action pemanggil.
 */
class PengukuranMutationSteps
{
    public function __construct(private PermissionResolver $resolver, private WriteAuditLog $audit, private PengukuranKinerjaPolicy $policy,
        private CalculatePengukuran $calculator, private EvaluateEvidence $evidence) {}

    /**
     * Urutan lock: aktor → role aktif (shared) → indikator (shared) → header → snapshot → jadwal → periode.
     * Izin dan gate bisnis diputuskan sesudah lock; `$decision` diisi sebelum penolakan dilempar agar audit penolakan memakai keputusan aktual.
     * Pemanggil wajib sudah berada dalam transaksi.
     *
     * @return array{0: User, 1: PengukuranKinerja, 2: JadwalSnapshot, 3: ?PeriodeJadwal, 4: array} aktor terkunci, header, snapshot, periode jadwal, state audit sebelum mutasi
     */
    public function lock(User $actor, string $id, string $permission, string $command, int $version, ?array &$decision): array
    {
        $actor = User::lockForUpdate()->findOrFail($actor->id);
        // Role aktif dikunci bersama agar rilis preset yang mencabut izin menunggu, dan izin dibaca dari ACL yang sudah commit.
        $actor->lockActiveRoles();
        // Timeline PJ stabil sejak guard hingga provenance dan snapshot tersimpan.
        $indicatorId = PengukuranKinerja::findOrFail($id)->indikator_id;
        $indicator = IndikatorKinerja::whereKey($indicatorId)->sharedLock()->firstOrFail();
        $p = PengukuranKinerja::lockForUpdate()->findOrFail($id);
        if ($p->indikator_id !== $indicator->id) {
            throw ValidationException::withMessages(['versi' => 'Konteks indikator telah berubah. Muat ulang pengukuran.']);
        }
        $p->setRelation('indikator', $indicator);
        $snapshot = JadwalSnapshot::lockForUpdate()->findOrFail($p->jadwal_snapshot_id);
        $snapshot->setRelation('jadwal', JadwalTahunan::lockForUpdate()->findOrFail($snapshot->jadwal_id));
        $period = PeriodeJadwal::where('jadwal_id', $snapshot->jadwal_id)->where('periode_id', $p->periode_id)->lockForUpdate()->first();
        $p->setRelation('jadwalSnapshot', $snapshot);
        $decision = $this->resolver->decide($actor, $permission, $p->targetUnitId());
        if (! $decision['allowed']) {
            throw new AuthorizationException('Izin tindakan tidak tersedia atau telah dicabut.');
        }
        $errors = $this->policy->businessErrors($actor, $p, $command);
        if ($p->versi !== $version) {
            $errors[] = 'Data telah berubah. Muat ulang sebelum mengulangi tindakan.';
        }
        if ($errors) {
            throw ValidationException::withMessages(['versi' => $errors]);
        }

        return [$actor, $p, $snapshot, $period, $this->auditState($p)];
    }

    /**
     * Simpan nilai/komponen ke header dan bukti baru untuk draf maupun pengajuan, tanpa versi, riwayat, atau audit.
     * `$path` diisi hanya bila berkas baru tersimpan agar pemanggil menghapusnya saat transaksi gagal;
     * `$decision` diganti keputusan unggah bila unggahan ditolak agar audit penolakan memakai keputusan aktual.
     */
    public function storeInput(User $actor, PengukuranKinerja $p, JadwalSnapshot $snapshot, array $data, ?string &$path, ?array &$decision): void
    {
        $definitions = $snapshot->komponen->map(fn ($c) => $c->only(['komponen_id', 'kode', 'label', 'peran', 'bobot', 'urutan']))->all();
        if ($snapshot->tipe_perhitungan !== 'manual' && array_key_exists('nilai', $data)) {
            throw ValidationException::withMessages(['nilai' => 'Nilai indikator nonmanual dihitung server dari komponen.']);
        }
        if ($snapshot->tipe_perhitungan === 'manual' && ! empty($data['komponen'])) {
            throw ValidationException::withMessages(['komponen' => 'Indikator manual memakai nilai langsung tanpa komponen semu.']);
        }
        $values = [];
        foreach ($data['komponen'] ?? [] as $value) {
            // UUID dibandingkan sebagai kunci array; samakan huruf agar cocok dengan ID snapshot tanpa mengubah formula.
            $key = strtolower($value['komponen_id']);
            if (array_key_exists($key, $values)) {
                throw ValidationException::withMessages(['komponen' => 'Komponen tidak boleh dikirim berulang.']);
            }
            $values[$key] = $value['nilai'];
        }
        if ($snapshot->tipe_perhitungan !== 'manual' && array_diff(array_column($definitions, 'komponen_id'), array_keys($values))) {
            throw ValidationException::withMessages(['komponen' => 'Seluruh komponen snapshot harus dikirim; gunakan nilai kosong untuk komponen yang belum diisi.']);
        }
        try {
            $result = $this->calculator->handle($snapshot->tipe_perhitungan, $snapshot->presisi, $definitions, $values, $data['nilai'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['nilai' => $exception->getMessage()]);
        }
        foreach ($definitions as $definition) {
            PengukuranKomponen::updateOrCreate(['pengukuran_id' => $p->id, 'komponen_id' => $definition['komponen_id']], ['nilai' => $values[$definition['komponen_id']] ?? null, 'updated_by' => $actor->id, 'updated_at' => now()]);
        }
        $p->fill([...$result, 'catatan' => $data['catatan'] ?? null,
            'alasan_tidak_dapat_dihitung' => $result['status_perhitungan'] === 'tidak_dapat_dihitung' ? ($data['alasan_tidak_dapat_dihitung'] ?? null) : null]);
        $this->appendEvidence($actor, $p, $data['bukti'] ?? null, $path, $decision);
    }

    /** Bekukan tenggat yang berlaku saat aksi reviu; revisi jadwal belakangan tidak mengubah jejak reviu. */
    public function reviewTrail(PeriodeJadwal $period): array
    {
        $reviewDate = today(config('app.business_timezone'))->toDateString();

        return ['reviu_terlambat' => $reviewDate > $period->reviu_selesai->toDateString(),
            'reviu_selesai' => $period->reviu_selesai->toDateString(), 'tanggal_reviu' => $reviewDate];
    }

    /**
     * Naikkan versi sekali, catat riwayat bila status berubah, lalu tulis audit sukses pada batas transaksi yang sama.
     * `$extra` melengkapi state sesudah (self_approval, jejak reviu) tanpa mengubah header.
     */
    public function finish(User $actor, PengukuranKinerja $p, string $event, string $reason, array $decision, array $before, array $extra): PengukuranKinerja
    {
        $p->versi++;
        $p->save();
        $p->unsetRelation('latestVersion');
        if ($before['status_alur'] !== $p->status_alur) {
            RiwayatPengukuran::create(['pengukuran_kinerja_id' => $p->id, 'user_id' => $actor->id, 'status_dari' => $before['status_alur'], 'status_ke' => $p->status_alur, 'catatan' => $reason]);
        }
        $this->writeAudit($actor, $p->id, $event, $reason, $decision, $before, [...$this->auditState($p), ...$extra]);

        return $p;
    }

    /**
     * Kompensasi setelah transaksi domain batal: hapus hanya berkas baru percobaan ini, lalu audit penolakan
     * otorisasi/validasi bisnis di luar rollback. Kegagalan lain tetap dilempar pemanggil tanpa audit.
     */
    public function compensate(User $actor, string $id, string $command, ?string $path, ?array $decision, Throwable $exception): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
        if (($exception instanceof AuthorizationException || $exception instanceof ValidationException) && PengukuranKinerja::whereKey($id)->exists()) {
            $this->writeAudit($actor, $id, 'pengukuran.ditolak', 'Tindakan '.$command.' ditolak.', $decision, null,
                ['tindakan_diminta' => $command, 'jenis_penolakan' => $exception instanceof AuthorizationException ? 'otorisasi' : 'validasi_bisnis',
                    'alasan_penolakan' => $exception instanceof ValidationException ? $exception->errors() : $exception->getMessage()]);
        }
    }

    public function writeAudit(User $actor, string $id, string $event, string $reason, ?array $decision, ?array $before, ?array $after): void
    {
        $this->audit->handle(['actor_type' => 'user', 'actor_id' => $actor->id, 'sumber' => 'manual', 'tindakan' => $event, 'objek_tipe' => 'pengukuran', 'objek_id' => $id,
            'alasan' => $reason, 'dasar_izin' => $decision, 'nilai_lama' => $before, 'nilai_baru' => $after]);
    }

    private function auditState(PengukuranKinerja $p): array
    {
        return [...$p->only(['status_alur', 'versi', 'nilai', 'sumber_nilai', 'status_perhitungan', 'catatan', 'alasan_tidak_dapat_dihitung']),
            'versi_pengajuan' => $p->latestVersion?->only(['id', 'nomor', 'diajukan_by', 'jalur_pengajuan', 'dasar_izin_pengajuan', 'disahkan_by', 'disahkan_at']),
            'komponen' => $p->komponen()->orderBy('komponen_id')->get(['komponen_id', 'nilai'])->toArray(),
            'bukti_dukungs' => $p->buktiDukungs()->current()->orderBy('id')->get()->map(fn ($b) => [...$b->only(['id', 'jenis_berkas_id', 'menggantikan_id', 'alasan_koreksi', 'mode', 'nama_asli', 'mime', 'ukuran_bytes', 'tautan']), 'panjang_teks' => $b->isi_teks === null ? null : mb_strlen($b->isi_teks)])->all()];
    }

    private function appendEvidence(User $actor, PengukuranKinerja $p, ?array $data, ?string &$path, ?array &$denialDecision): void
    {
        if (! $data) {
            return;
        }
        $decision = $this->resolver->decide($actor, 'berkas:upload', $p->targetUnitId());
        if (in_array($decision['reason'], ['explicit_deny', 'unknown_permission', 'inactive_user', 'no_role', 'inactive_unit', 'invalid_scope'], true)) {
            $denialDecision = $decision;
            throw new AuthorizationException('Izin unggah bukti telah dicabut.');
        }
        $settings = $this->evidence->settings();
        $requirement = null;
        if (! empty($data['jenis_berkas_id'])) {
            $requirement = JenisBerkas::whereKey($data['jenis_berkas_id'])->where('aktif', true)->where('tahap', 'pengukuran')
                ->where(fn ($q) => $q->whereNull('indikator_id')->orWhere('indikator_id', $p->indikator_id))->lockForUpdate()->first();
            if (! $requirement || ! $requirement->{'izinkan_'.$data['mode']}) {
                throw ValidationException::withMessages(['bukti.mode' => 'Mode atau persyaratan bukti tidak berlaku untuk pengukuran ini.']);
            }
        }
        $predecessor = null;
        $correctionReason = null;
        if (! empty($data['menggantikan_id'])) {
            $predecessor = $p->buktiDukungs()->current()->whereKey($data['menggantikan_id'])->lockForUpdate()->first();
            if (! $predecessor || $predecessor->jenis_berkas_id !== $requirement?->id) {
                throw ValidationException::withMessages(['bukti.menggantikan_id' => 'Bukti yang diganti harus masih berlaku pada pengukuran dan persyaratan yang sama.']);
            }
            $correctionReason = trim((string) ($data['alasan_koreksi'] ?? ''));
            if ($correctionReason === '') {
                throw ValidationException::withMessages(['bukti.alasan_koreksi' => 'Alasan koreksi bukti wajib diisi.']);
            }
        }
        // UUID baru dan pendahulu yang masih berlaku membentuk rantai tanpa siklus/fork di bawah lock header.
        $attributes = ['berkasable_type' => 'pengukuran', 'berkasable_id' => $p->id, 'jenis_berkas_id' => $requirement?->id,
            'menggantikan_id' => $predecessor?->id, 'alasan_koreksi' => $correctionReason,
            'mode' => $data['mode'], 'uploaded_by' => $actor->id, 'created_at' => now()];
        if ($data['mode'] === 'file') {
            if (! $settings['unggahan_aktif']) {
                throw ValidationException::withMessages(['bukti.file' => 'Unggahan file sedang dinonaktifkan. Mode tautan/teks tetap mengikuti persyaratannya.']);
            }
            $max = $requirement?->ukuran_maks_kb ?? $settings['ukuran_maks_kb'];
            $formats = $requirement?->format_diizinkan ?: $settings['format_diizinkan'];
            Validator::make($data, ['file' => ['required', 'file', 'max:'.$max, 'mimes:'.$formats]])->validate();
            $file = $data['file'];
            $path = $file->store('berkas', 'local');
            if (! is_string($path)) {
                throw new \RuntimeException('Penyimpanan bukti gagal.');
            }
            $attributes += ['nama_asli' => $file->getClientOriginalName(), 'path' => $path, 'mime' => $file->getMimeType(), 'ukuran_bytes' => $file->getSize()];
        } elseif ($data['mode'] === 'tautan') {
            $attributes['tautan'] = $data['tautan'];
        } else {
            $attributes['isi_teks'] = $data['isi_teks'];
        }
        BuktiDukung::create($attributes);
    }
}
