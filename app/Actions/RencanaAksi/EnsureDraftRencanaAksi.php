<?php

namespace App\Actions\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EnsureDraftRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly IndikatorArsipGuard $arsipGuard,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Membuat header draf secara idempoten untuk satu kombinasi indikator × tahun.
     *
     * Urutan kunci deterministik di dalam transaksi (anti-deadlock, sama
     * dengan `SimpanTargetPeriode`): baris ACL pengguna pengunci, calon
     * header rencana aksi (berdasarkan pasangan indikator × tahun, mungkin
     * belum ada sehingga mengunci nihil tetapi tetap menjaga urutan
     * akuisisi), indikator, jadwal tahunan, snapshot beku, lalu
     * cek-idempoten header. Tanpa retry: antrean kunci
     * menserialkan transaksi bersamaan, bukan 40P01.
     *
     * Unit disalin dari indikator terkunci, jadwal diambil dari jadwal aktif
     * tahun tersebut, dan penanggung jawab diisi PIC efektif pada hari ini
     * zona Asia/Makassar. Indikator arsip dan ketiadaan jadwal aktif ditolak
     * validasi. Snapshot beku wajib ada untuk jadwal pernah-aktif; tanpanya
     * pembuatan ditolak fail-closed. Jalur unit-scoped PIC mensyaratkan pemanggil adalah PIC
     * efektif hari ini dan hari ini di dalam jendela rencana aksi; jalur
     * Perencanaan global mengikuti kewenangan resmi tanpa syarat PIC/jendela
     * (tetap tunduk pada penutupan tahun). Keberadaan header diperiksa di
     * bawah kunci sehingga pemanggilan ulang mengembalikan baris yang sama
     * tanpa duplikat.
     */
    public function handle(User $actor, string $indikatorId, int $tahun): RencanaAksi
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            return DB::transaction(function () use ($actor, $indikatorId, $tahun, &$dasarIzin) {
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_CREATE);
                /** @var User|null $pengunci */
                $pengunci = $kunci['aktor'];
                if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
                    $dasarIzin = $kunci['keputusan']->toAuditBasis();
                    throw new AuthorizationException('Akun pengguna tidak aktif.');
                }

                // T5: kunci calon header lebih dulu (mungkin nihil) agar urutan
                // akuisisi RencanaAksi → Indikator → Jadwal sama dengan
                // SimpanTargetPeriode (header → indikator → jadwal). Tanpa ini,
                // Ensure (Indikator → Header) vs Simpan (Header → Indikator)
                // saling menunggu = 40P01 saat create dan update bersamaan.
                RencanaAksi::where('indikator_id', $indikatorId)->where('tahun', $tahun)->lockForUpdate()->first();

                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($indikatorId);
                $indikator->loadMissing('sasaranStrategis');
                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                $unitId = (string) $indikator->unit_id;
                $keputusan = $this->resolver->decide($pengunci, PermissionCodes::RENCANA_AKSI_CREATE, $unitId);
                $dasarIzin = $keputusan;
                if (! $keputusan['allowed']) {
                    throw new AuthorizationException('Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.');
                }

                if (! DB::table('unit')->where('id', $unitId)->where('status', 'aktif')->exists()) {
                    throw ValidationException::withMessages(['indikator_id' => 'Unit pemilik indikator berstatus nonaktif.']);
                }

                $renstraId = $indikator->sasaranStrategis?->renstra_id;
                if (! is_string($renstraId) || $renstraId === '') {
                    throw ValidationException::withMessages(['indikator_id' => 'Sasaran strategis indikator tidak memiliki Renstra induk yang sah.']);
                }

                $jadwal = JadwalTahunan::where('renstra_id', $renstraId)
                    ->where('tahun', $tahun)
                    ->where('status', 'aktif')
                    ->lockForUpdate()
                    ->first();
                if (! $jadwal instanceof JadwalTahunan) {
                    throw ValidationException::withMessages(['tahun' => 'Jadwal tahunan aktif untuk tahun ini belum tersedia.']);
                }

                $hariIni = today(config('app.business_timezone'))->toDateString();
                // Resolver PJ kanonis; writer PJ juga mengunci indikator FOR UPDATE,
                // sehingga kunci indikator di atas sudah menyerialkan pergantian PJ.
                $pic = PenugasanIndikator::effectiveOn($hariIni)->where('indikator_id', $indikator->id)->first();
                if (! $pic instanceof PenugasanIndikator) {
                    throw ValidationException::withMessages(['indikator_id' => 'Penugasan PIC efektif belum tersedia untuk indikator ini.']);
                }

                $this->pastikanDapatMembuat($pengunci, $keputusan, $indikator, $jadwal, $pic);
                $snapshotDraf = $this->pastikanSnapshotTersedia($jadwal, $indikator);

                $existing = RencanaAksi::where('indikator_id', $indikator->id)
                    ->where('tahun', $tahun)
                    ->lockForUpdate()
                    ->first();
                if ($existing instanceof RencanaAksi) {
                    return $existing;
                }

                // Kunci indikator FOR UPDATE memblokir INSERT header lain (FK butuh
                // FOR KEY SHARE), jadi 23505 di sini berarti bug: gagal tertutup.
                $created = RencanaAksi::create([
                    'indikator_id' => $indikator->id,
                    'tahun' => $tahun,
                    'unit_id' => $unitId,
                    'jadwal_tahunan_id' => $jadwal->id,
                    // Jepit konteks awal draf; null bila jadwal belum pernah
                    // aktif (tanpa snapshot).
                    'snapshot_draf_id' => $snapshotDraf?->id,
                    'penanggung_jawab_id' => $pic->user_id,
                    'uraian' => null,
                    'status_alur' => RencanaAksi::STATUS_DRAFT,
                    'versi' => 1,
                    'alasan_revisi' => null,
                    'alasan_deviasi_pk' => null,
                    'created_by' => $pengunci->id,
                ]);

                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'rencana_aksi.buat',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $created->id,
                    nilaiLama: null,
                    nilaiBaru: $this->auditState($created),
                    alasan: "Membuat draf rencana aksi indikator '{$indikator->kode}' tahun {$tahun}.",
                    dasarIzin: $dasarIzin,
                );

                return $created;
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'rencana_aksi.buat_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) Str::uuid(),
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Pembuatan draf rencana aksi ditolak.'),
                    dasarIzin: is_array($dasarIzin) ? $dasarIzin : null,
                );
            }

            throw $exception;
        }
    }

    /**
     * Menegakkan batas mutation create: penutupan tahun untuk semua jalur,
     * lalu PIC efektif + jendela RA khusus jalur unit-scoped.
     *
     * @param  array<string, mixed>  $keputusan
     */
    private function pastikanDapatMembuat(User $pengunci, array $keputusan, IndikatorKinerja $indikator, JadwalTahunan $jadwal, PenugasanIndikator $pic): void
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $penutupan = $jadwal->penutupan?->toDateString();
        if (is_string($penutupan) && $hariIni > $penutupan && ! $this->dalamKoreksiSah($indikator, $jadwal)) {
            throw ValidationException::withMessages(['jendela' => 'Tahun jadwal telah ditutup; pembuatan memerlukan sesi koreksi resmi.']);
        }

        if ($this->jalurPerencanaan($pengunci, $keputusan)) {
            return;
        }

        if ((string) $pic->user_id !== (string) $pengunci->id) {
            throw ValidationException::withMessages(['jendela' => 'Tindakan ini memerlukan penugasan PIC yang efektif.']);
        }

        $mulai = $jadwal->rencana_aksi_mulai?->toDateString();
        $selesai = $jadwal->rencana_aksi_selesai?->toDateString();
        if (! is_string($mulai) || ! is_string($selesai) || $hariIni < $mulai || $hariIni > $selesai) {
            throw ValidationException::withMessages(['jendela' => 'Jendela penyusunan rencana aksi periode ini sudah ditutup.']);
        }
    }

    /**
     * Snapshot beku wajib ada begitu jadwal pernah diaktifkan; tanpanya
     * pembuatan draf ditolak fail-closed (audit buat_ditolak di pemanggil).
     * Bila snapshot tersedia tetapi unit bekunya berbeda dari unit master
     * indikator saat ini (indikator pindah unit pasca-aktivasi), pembuatan
     * juga ditolak sampai snapshot koreksi yang selaras tersedia — identitas
     * unit tahun itu mengikuti snapshot beku, bukan master berjalan,
     * konsisten dengan identitas target pengukuran (`targetUnitId` memakai
     * `jadwal_snapshot.unit_id`) dan prasyarat pengajuan yang mensyaratkan
     * `rencana_aksi.unit_id` cocok dengan unit snapshot pengukuran.
     *
     * Mengembalikan snapshot terbaru (null bila jadwal belum pernah aktif)
     * agar pemanggil dapat menjepit konteks awal draf (F2/F3 Review6 T2).
     */
    private function pastikanSnapshotTersedia(JadwalTahunan $jadwal, IndikatorKinerja $indikator): ?JadwalSnapshot
    {
        if (! $this->jadwalPernahDiaktifkan($jadwal)) {
            return null;
        }

        $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal->id)
            ->where('indikator_id', $indikator->id)
            ->orderByDesc('nomor_versi')
            ->lockForUpdate()
            ->first();

        if (! $snapshot instanceof JadwalSnapshot) {
            throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; pembuatan draf ditolak.']);
        }

        $unitBeku = (string) ($snapshot->unit_id ?? '');
        if ($unitBeku !== '' && $unitBeku !== (string) $indikator->unit_id) {
            throw ValidationException::withMessages(['snapshot' => 'Unit pemilik indikator telah berpindah setelah aktivasi jadwal; pembuatan draf ditolak sampai snapshot koreksi tersedia.']);
        }

        return $snapshot;
    }

    private function jadwalPernahDiaktifkan(JadwalTahunan $jadwal): bool
    {
        return $jadwal->is_terkunci
            || $jadwal->activated_at !== null
            || in_array($jadwal->status, ['aktif', 'ditutup'], true);
    }

    /**
     * @param  array<string, mixed>  $keputusan
     */
    private function jalurPerencanaan(User $pengunci, array $keputusan): bool
    {
        if (! ($keputusan['allowed'] ?? false)) {
            return false;
        }
        $roleIds = $keputusan['roles'] ?? [];

        if ($roleIds === []) {
            return false;
        }

        return $pengunci->roles()
            ->whereIn('roles.id', (array) $roleIds)
            ->whereIn('kode', ['perencanaan', 'superadmin'])
            ->exists();
    }

    private function dalamKoreksiSah(IndikatorKinerja $indikator, JadwalTahunan $jadwal): bool
    {
        if (! $jadwal->koreksi_mulai instanceof CarbonInterface || ! $jadwal->koreksi_sampai instanceof CarbonInterface) {
            return false;
        }
        if (! now()->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)) {
            return false;
        }
        $lingkup = $jadwal->lingkup_koreksi ?? [];
        if (! in_array('rencana_aksi', $lingkup['jenis_objek'] ?? [], true)) {
            return false;
        }
        if (! in_array($indikator->id, $lingkup['indikator_ids'] ?? [], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(RencanaAksi $rencanaAksi): array
    {
        return $rencanaAksi->only(['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'penanggung_jawab_id', 'status_alur', 'versi']);
    }
}
