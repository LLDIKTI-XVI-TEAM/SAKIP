<?php

namespace App\Actions\PerjanjianKinerja;

use App\Actions\TargetTahunan\SaveTargetTahunan;
use App\Http\Requests\PerjanjianKinerja\StoreVersiPerjanjianKinerjaRequest;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\Renstra;
use App\Models\RenstraPk;
use App\Models\RenstraPkVersi;
use App\Models\RenstraPkVersiTarget;
use App\Models\TargetKinerja;
use App\Models\TargetRenstra;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Jadwal\JadwalSnapshotWriter;
use App\Services\PerjanjianKinerja\PerjanjianKinerjaAttachmentService;
use App\Support\AuditReason;
use App\Support\DeviasiTargetRenstra;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use App\Support\TargetTahunanDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Menyetujui revisi PK berversi: membentuk versi PK baru beserta target seluruh indikator aktif dan lampirannya,
 * serta menerbitkan versi snapshot untuk indikator yang targetnya berubah.
 *
 * - Urutan lock: aktor per izin → konfigurasi periode → PK → indikator aktif → Renstra shared → jadwal → baca ulang
 *   himpunan indikator aktif → snapshot terbaru → target master. Indikator sebelum jadwal dan Renstra sama dengan
 *   Action lain yang mengunci keduanya; lock PK menyerialkan revisi dengan unggah dan hapus lampiran PK.
 * - Dua token: nomor versi terakhir (dicek setelah lock PK) dan sidik snapshot dasar (dicek setelah snapshot terkunci),
 *   sehingga versi tidak menyimpan nilai dasar yang tidak dilihat pengguna.
 * - Nilai dasar setiap indikator = target snapshot terbaru, bukan master. Entri yang sama dengan nilai dasar dibuang;
 *   sisanya adalah pengubah target dan hanya untuk itu berlaku gerbang jadwal, Renstra, presisi, deviasi Renstra,
 *   dan periode mulai. Revisi tanpa pengubah target tidak menerbitkan snapshot dan tidak mengubah master.
 * - Periode mulai = periode terendah yang belum dilaporkan, dinaikkan per indikator ke periode mulai versi pendahulu.
 * - Snapshot baru menyalin versi pendahulu dan hanya mengganti target; master ikut diperbarui bila berbeda.
 * - Revisi pertama juga membentuk versi 1 dari target snapshot versi 1 dan daftar lampiran induk PK saat ini.
 */
class SetujuiRevisiPerjanjianKinerja
{
    /** Revisi target maupun dokumen memerlukan keempat izin sekaligus; deny pada salah satunya menolak. */
    public const IZIN = [PermissionCodes::PK_UPDATE, PermissionCodes::TARGET_UPDATE, PermissionCodes::INDIKATOR_READ, PermissionCodes::BERKAS_UPLOAD];

    public const PESAN_JADWAL = 'Target hanya dapat direvisi selama jadwal aktif dan tahun belum ditutup, termasuk saat sesi koreksi. Revisi dokumen tetap dapat disimpan tanpa mengubah target.';

    public const PESAN_RENSTRA = 'Renstra nonaktif atau diarsipkan, sehingga target tidak dapat direvisi. Revisi dokumen tetap dapat disimpan tanpa mengubah target.';

    public const PESAN_TERLAPOR = 'Seluruh periode sudah dilaporkan untuk semua indikator, sehingga target tidak dapat direvisi. Revisi dokumen tetap dapat disimpan tanpa mengubah target.';

    private const PESAN_VERSI = 'Versi PK telah berubah. Muat ulang halaman sebelum menyimpan revisi.';

    private const PESAN_SIBUK = 'Data sedang diubah pengguna lain. Muat ulang halaman PK lalu coba lagi.';

    public function __construct(private readonly ResolveLockedActor $lockedActor, private readonly AuditLogger $audit,
        private readonly JadwalSnapshotWriter $snapshots, private readonly PerjanjianKinerjaAttachmentService $lampiran) {}

    /** @param  array<string, mixed>  $data */
    public function handle(User $actor, string $pkId, array $data): RenstraPkVersi
    {
        $pkId = strtolower($pkId);
        /** @var array<string, array<string, mixed>> $basis */
        $basis = [];
        /** @var list<string> $storedPaths */
        $storedPaths = [];
        $berhasil = false;
        try {
            // Closure by reference: keputusan izin dan path file harus terbaca di catch/finally setelah transaksi gagal.
            $versi = DB::transaction(function () use ($actor, $pkId, $data, &$basis, &$storedPaths): RenstraPkVersi {
                return $this->setujui($actor, $pkId, $data, $basis, $storedPaths);
            });
            $berhasil = true;

            return $versi;
        } catch (AuthorizationException|ValidationException|QueryException|DeadlockException $exception) {
            // Deadlock/serialization/lock timeout menjadi konflik aman dan unique nomor versi ganda menjadi token usang;
            // QueryException lain tetap error internal. Laravel membungkus deadlock transaksi bertingkat sebagai DeadlockException.
            if ($exception instanceof QueryException || $exception instanceof DeadlockException) {
                $kode = $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null;
                if ($exception instanceof QueryException && ! in_array($kode, ['40P01', '40001', '55P03', '23505'], true)) {
                    throw $exception;
                }
                $exception = $kode === '23505'
                    ? ValidationException::withMessages(['expected_nomor_versi' => self::PESAN_VERSI])->status(409)
                    : ValidationException::withMessages(['jadwal' => self::PESAN_SIBUK]);
            }
            $alasanPenolakan = 'izin_ditolak';
            $field = [];
            if ($exception instanceof ValidationException) {
                $field = array_values(array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($exception->errors()))));
                $alasanPenolakan = $exception->status === 409 || in_array('jadwal', $field, true) ? 'konflik' : 'aturan_revisi';
            }
            // Di luar rollback, tepat satu kali per percobaan, memakai keputusan izin yang dibaca ulang.
            $this->audit->catat(actor: $actor, tindakan: 'renstra_pk_versi.setujui_ditolak', objekTipe: 'renstra_pk',
                objekId: Str::isUuid($pkId) ? $pkId : (string) Str::uuid(),
                nilaiBaru: ['tindakan_diminta' => 'renstra_pk_versi.setujui', 'alasan_penolakan' => $alasanPenolakan, 'field' => $field],
                dasarIzin: $basis !== [] ? $basis : null);
            throw $exception;
        } finally {
            // File yang sudah tersimpan dibersihkan pada setiap kegagalan, termasuk exception yang dilempar ulang.
            if (! $berhasil) {
                $this->lampiran->hapusFile($storedPaths);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $basis
     * @param  list<string>  $storedPaths
     */
    private function setujui(User $actor, string $pkId, array $data, array &$basis, array &$storedPaths): RenstraPkVersi
    {
        /** @var array<string, PermissionDecision> $keputusan */
        $keputusan = [];
        foreach (self::IZIN as $kode) {
            $keputusan[$kode] = $this->lockedActor->handle($actor, $kode)['keputusan'];
        }
        $basis = array_map(fn (PermissionDecision $decision): array => $decision->toAuditBasis(), $keputusan);
        if (collect($keputusan)->contains(fn (PermissionDecision $decision): bool => ! $decision->allowed)) {
            throw new AuthorizationException(StoreVersiPerjanjianKinerjaRequest::PESAN_IZIN);
        }
        if (StoreVersiPerjanjianKinerjaRequest::fieldAsing($data)) {
            throw ValidationException::withMessages(['revisi' => StoreVersiPerjanjianKinerjaRequest::PESAN_FIELD]);
        }
        $data = Validator::make($data, StoreVersiPerjanjianKinerjaRequest::inputRules(), StoreVersiPerjanjianKinerjaRequest::inputMessages())->validate();

        Periode::lockConfiguration();
        $pk = RenstraPk::whereKey($pkId)->lockForUpdate()->firstOrFail();
        if (! $pk->dipakaiJadwalAktif()) {
            throw ValidationException::withMessages(['pk' => 'PK belum dikunci jadwal. Ubah PK lewat form edit dan editor target.']);
        }
        $nomorTerakhir = (int) RenstraPkVersi::where('renstra_pk_id', $pk->id)->max('nomor_versi');
        if ($nomorTerakhir !== (int) $data['expected_nomor_versi']) {
            throw ValidationException::withMessages(['expected_nomor_versi' => self::PESAN_VERSI])->status(409);
        }

        $jadwal = $pk->resolveJadwalTahunan() ?? throw new RuntimeException('PK terkunci tanpa jadwal terkait.');
        $indikatorIds = $jadwal->indikatorAktif()->orderBy('indikator_kinerjas.id')->lockForUpdate()->pluck('indikator_kinerjas.id')->all();
        $renstra = Renstra::whereKey($pk->renstra_id)->sharedLock()->firstOrFail();
        $jadwal = JadwalTahunan::whereKey($jadwal->id)->lockForUpdate()->firstOrFail();
        // Indikator yang ditambahkan sebelum jadwal terkunci terdeteksi di sini; ID baru tidak dikunci sambil memegang jadwal.
        if ($jadwal->indikatorAktif()->orderBy('indikator_kinerjas.id')->pluck('indikator_kinerjas.id')->all() !== $indikatorIds) {
            throw ValidationException::withMessages(['jadwal' => 'Daftar indikator PK berubah saat revisi disimpan. Muat ulang halaman PK lalu coba lagi.']);
        }
        $dasar = $jadwal->snapshotDasar(kunci: true)->load('periodeMulai');
        $nilaiDasar = [];
        foreach ($indikatorIds as $id) {
            $nilaiDasar[$id] = self::nilai($dasar->get($id)?->getRawOriginal('target'))
                ?? throw new RuntimeException("Indikator aktif {$id} tanpa snapshot bertarget.");
        }
        if (! hash_equals(JadwalTahunan::tokenDasar($dasar), $data['expected_dasar'])) {
            throw ValidationException::withMessages(['expected_dasar' => 'Target dasar PK telah berubah sejak halaman dibuka. Muat ulang halaman sebelum menyimpan revisi.'])->status(409);
        }

        // Pengubah target: indikator_id => [indeks payload asli, nilai]. Indeks dibawa agar galat per entri sama dengan
        // galat wildcard FormRequest walau entri sebelumnya dibuang.
        /** @var array<string, array{0: int, 1: string}> $pengubah */
        $pengubah = [];
        foreach ($data['target'] ?? [] as $i => $entri) {
            $id = strtolower($entri['indikator_id']);
            if (! array_key_exists($id, $nilaiDasar)) {
                throw ValidationException::withMessages(['target' => 'Indikator tidak termasuk PK ini.']);
            }
            $nilai = (string) TargetTahunanDecimal::normalize($entri['nilai'], "target.{$i}.nilai");
            if ($nilai !== $nilaiDasar[$id]) {
                // Snapshot baru menyalin presisi pendahulu, jadi entri dibatasi presisi snapshot terbaru.
                TargetTahunanDecimal::assertPrecision($nilai, $dasar[$id]->presisi, "target.{$i}.nilai");
                $pengubah[$id] = [$i, $nilai];
            }
        }

        $periode = $jadwal->periodePertamaBelumDilaporkan();
        $master = collect();
        $masterLama = [];
        $periodeMulai = [];
        $pengukuran = [];
        if ($pengubah !== []) {
            if (($penghalang = self::penghalangTarget($jadwal, $renstra)) !== null) {
                throw ValidationException::withMessages(['target' => $penghalang]);
            }
            $ids = array_keys($pengubah);
            $master = TargetKinerja::whereIn('indikator_kinerja_id', $ids)->where('tahun', $jadwal->tahun)->orderBy('id')->lockForUpdate()->get()->keyBy('indikator_kinerja_id');
            $indikator = IndikatorKinerja::whereIn('id', $ids)->get()->keyBy('id');
            $targetRenstra = TargetRenstra::whereIn('indikator_id', $ids)->where('tahun', $jadwal->tahun)->pluck('nilai', 'indikator_id');
            foreach ($pengubah as $id => [$i, $nilai]) {
                $masterLama[$id] = self::nilai(($master->get($id) ?? throw new RuntimeException("Indikator aktif {$id} tanpa target PK."))->getRawOriginal('target_tahunan'));
                if ($nilai !== $masterLama[$id]) {
                    TargetTahunanDecimal::assertPrecision($nilai, $indikator[$id]->presisi, "target.{$i}.nilai");
                }
                // Deviasi Renstra dinilai untuk setiap pengubah target, walau nilainya sama dengan master.
                $arah = $indikator[$id]->arah;
                $renstraNilai = self::nilai($targetRenstra->get($id));
                if (DeviasiTargetRenstra::status($arah, $nilai, $renstraNilai) === DeviasiTargetRenstra::MENYIMPANG) {
                    throw ValidationException::withMessages(["target.{$i}.nilai" => sprintf(SaveTargetTahunan::PESAN_DEVIASI,
                        $arah === 'turun_baik' ? 'di atas' : 'di bawah', $jadwal->tahun, str_replace('.', ',', (string) $renstraNilai))]);
                }
            }
            if ($periode === null) {
                throw ValidationException::withMessages(['target' => self::PESAN_TERLAPOR]);
            }
            foreach ($ids as $id) {
                $mulai = self::periodeMulaiIndikator($periode->periode, $dasar[$id]);
                $periodeMulai[$id] = $mulai->id;
                $pengukuran[$id] = $jadwal->pengukuranBelumDisahkanSebelum($id, $mulai)->pluck('pengukuran_kinerjas.id')->all();
            }
            if (array_merge(...array_values($pengukuran)) !== [] && ! ($data['konfirmasi_pengukuran_sebelumnya'] ?? false)) {
                throw ValidationException::withMessages(['konfirmasi_pengukuran_sebelumnya' => 'Ada pengukuran belum disahkan pada periode sebelum periode mulai. Periksa daftarnya lalu centang konfirmasi.']);
            }
        }

        $berkasAwalIds = null;
        if ($nomorTerakhir === 0) {
            // Lampiran induk yang ada saat lock PK dipegang; unggahan PK berikutnya menunggu lock yang sama.
            $berkasAwalIds = $pk->berkas()->orderBy('id')->pluck('id')->all();
            $awal = JadwalSnapshot::where('jadwal_id', $jadwal->id)->whereIn('indikator_id', $indikatorIds)->where('nomor_versi', 1)->pluck('target', 'indikator_id');
            $nilaiAwal = collect($indikatorIds)->mapWithKeys(fn (string $id): array => [$id => self::nilai($awal->get($id))
                ?? throw new RuntimeException("Indikator aktif {$id} tanpa snapshot versi 1 bertarget.")])->all();
            $this->simpanTarget(RenstraPkVersi::create(['renstra_pk_id' => $pk->id, 'nomor_versi' => 1, 'berkas_awal_ids' => $berkasAwalIds]), $nilaiAwal);
        }

        foreach ($pengubah as $id => [, $nilai]) {
            if ($nilai !== $masterLama[$id]) {
                $master[$id]->fill(['target_tahunan' => $nilai, 'updated_by' => $actor->id])->save();
            }
        }

        $alasan = AuditReason::sanitize($data['alasan']);
        $versi = RenstraPkVersi::create(['renstra_pk_id' => $pk->id, 'nomor_versi' => max($nomorTerakhir, 1) + 1,
            'nomor_surat' => $data['nomor_surat'], 'tanggal_surat' => $data['tanggal_surat'], 'periode_mulai_id' => $periode?->periode_id,
            'alasan' => $alasan, 'disetujui_oleh' => $actor->id, 'disetujui_pada' => now()]);
        $targetBaru = array_map(fn (array $entri): string => $entri[1], $pengubah);
        $this->simpanTarget($versi, array_merge($nilaiDasar, $targetBaru));

        $rujukan = $versi->nomor_surat.' tanggal '.$versi->tanggal_surat?->toDateString();
        $snapshotTerbit = [];
        foreach ($targetBaru as $id => $nilai) {
            $snapshotTerbit[] = $this->snapshots->terbitkanRevisiTarget($actor, $dasar[$id], $nilai, $periodeMulai[$id],
                $alasan, $rujukan, $basis, $versi->id, $pengukuran[$id]);
        }

        $this->lampiran->simpanLampiran($versi, array_map(fn (array $item): array => ['mode' => 'file', ...$item], $data['lampiran']),
            $actor, $storedPaths, $keputusan[PermissionCodes::BERKAS_UPLOAD], $keputusan[PermissionCodes::PK_UPDATE]);

        $this->audit->catat(actor: $actor, tindakan: 'renstra_pk_versi.setujui', objekTipe: 'renstra_pk', objekId: $pk->id,
            nilaiLama: ['nomor_versi_sebelumnya' => $nomorTerakhir,
                'target' => collect($targetBaru)->map(fn (string $nilai, string $id): array => ['snapshot' => $nilaiDasar[$id], 'master' => $masterLama[$id]])->all()],
            nilaiBaru: ['renstra_pk_versi_id' => $versi->id, 'nomor_versi' => $versi->nomor_versi, 'nomor_surat' => $versi->nomor_surat,
                'tanggal_surat' => $versi->tanggal_surat?->toDateString(), 'periode_mulai_id' => $versi->periode_mulai_id,
                'versi_1_dibentuk' => $berkasAwalIds !== null, ...($berkasAwalIds !== null ? ['berkas_awal_ids' => $berkasAwalIds] : []),
                'target' => $targetBaru, 'snapshot_terbit' => $snapshotTerbit, 'lampiran' => $versi->berkas()->orderBy('id')->pluck('id')->all()],
            alasan: $alasan, dasarIzin: $basis);

        return $versi;
    }

    /** Gerbang jadwal dan Renstra untuk pengubah target; juga dipakai pratinjau form revisi di halaman PK. */
    public static function penghalangTarget(JadwalTahunan $jadwal, Renstra $renstra): ?string
    {
        return match (true) {
            $jadwal->status !== 'aktif' || $jadwal->tahunDitutup() => self::PESAN_JADWAL,
            ! in_array($renstra->status, [Renstra::STATUS_DRAFT, Renstra::STATUS_AKTIF], true) => self::PESAN_RENSTRA,
            default => null,
        };
    }

    /**
     * Periode mulai versi snapshot indikator: periode global revisi, tetapi tidak lebih awal dari periode mulai
     * versi pendahulunya.
     */
    public static function periodeMulaiIndikator(Periode $periodeGlobal, JadwalSnapshot $pendahulu): Periode
    {
        $mulaiLama = $pendahulu->periodeMulai;

        return $mulaiLama !== null && $mulaiLama->urutan > $periodeGlobal->urutan ? $mulaiLama : $periodeGlobal;
    }

    /** Nilai numeric tersimpan dalam bentuk kanonis (mis. `60.000000000000` menjadi `60`) agar dapat dibandingkan sebagai string. */
    private static function nilai(mixed $raw): ?string
    {
        return TargetTahunanDecimal::normalize($raw === null ? null : (string) $raw);
    }

    /** @param  array<string, string>  $nilai  indikator_id => nilai ternormalisasi */
    private function simpanTarget(RenstraPkVersi $versi, array $nilai): void
    {
        RenstraPkVersiTarget::insert(array_map(fn (string $id): array => ['id' => (string) Str::uuid(), 'renstra_pk_versi_id' => $versi->id,
            'indikator_id' => $id, 'nilai' => $nilai[$id]], array_keys($nilai)));
    }
}
