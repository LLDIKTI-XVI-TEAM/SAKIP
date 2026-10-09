<?php

namespace App\Actions\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\RencanaAksi;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
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
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly KonteksBekuRencanaAksi $konteks,
    ) {}

    /**
     * Membuat header draf secara idempoten untuk satu kombinasi indikator × tahun.
     *
     * Urutan kunci deterministik di dalam transaksi (anti-deadlock; header
     * lalu indikator sama dengan `SimpanTargetPeriode`): baris ACL pengguna
     * pengunci, calon header rencana aksi (berdasarkan pasangan indikator ×
     * tahun, mungkin belum ada sehingga mengunci nihil tetapi tetap menjaga
     * urutan akuisisi), indikator, unit (FOR SHARE, kompatibel dengan FOR
     * SHARE unit di jalur simpan), jadwal tahunan, snapshot beku, lalu
     * cek-idempoten header. Tanpa retry: antrean kunci
     * menserialkan transaksi bersamaan, bukan 40P01.
     *
     * Unit disalin dari indikator terkunci, jadwal diambil dari jadwal aktif
     * tahun tersebut, dan penanggung jawab diisi PIC efektif pada hari ini
     * zona Asia/Makassar. Indikator arsip dan ketiadaan jadwal aktif ditolak
     * validasi. Snapshot beku jadwal aktif wajib ada; tanpanya
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

                // Kunci calon header lebih dulu (mungkin nihil) agar urutan
                // akuisisi RencanaAksi → Indikator → Jadwal sama dengan
                // SimpanTargetPeriode (header → indikator → jadwal). Tanpa ini,
                // Ensure (Indikator → Header) vs Simpan (Header → Indikator)
                // saling menunggu = 40P01 saat create dan update bersamaan.
                RencanaAksi::where('indikator_id', $indikatorId)->where('tahun', $tahun)->lockForUpdate()->first();

                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($indikatorId);
                $indikator->loadMissing('sasaranStrategis');
                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                $unitId = (string) $indikator->unit_id;
                $keputusan = $this->resolver->resolve($pengunci, PermissionCodes::RENCANA_AKSI_CREATE, $unitId);
                $dasarIzin = $keputusan->toAuditBasis();
                if (! $keputusan->allowed) {
                    throw new AuthorizationException('Izin pembuatan rencana aksi tidak tersedia atau telah dicabut.');
                }

                // FOR SHARE berkonflik dengan kunci writer status unit, sehingga
                // penonaktifan yang sedang berjalan tidak lolos di antara baca dan INSERT.
                $unit = Unit::whereKey($unitId)->sharedLock()->first();
                if (! $unit instanceof Unit || $unit->status !== 'aktif') {
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

                $alasan = $this->jendela->alasanTolak($pengunci, $keputusan, $indikator, $jadwal, 'pembuatan');
                if ($alasan !== null) {
                    throw ValidationException::withMessages(['jendela' => $alasan]);
                }
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
                    // Jepit konteks awal draf.
                    'snapshot_draf_id' => $snapshotDraf->id,
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
                    // UUID huruf besar sah sebagai input; audit memakai bentuk kanonis PostgreSQL.
                    nilaiBaru: ['indikator_id' => strtolower($indikatorId), 'tahun' => $tahun],
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Pembuatan draf rencana aksi ditolak.'),
                    dasarIzin: is_array($dasarIzin) ? $dasarIzin : null,
                );
            }

            throw $exception;
        }
    }

    /**
     * Snapshot beku wajib ada (jadwal di sini selalu aktif); tanpanya
     * pembuatan draf ditolak fail-closed (audit buat_ditolak di pemanggil).
     * Bila snapshot tersedia tetapi unit bekunya berbeda dari unit master
     * indikator saat ini (indikator pindah unit pasca-aktivasi), pembuatan
     * juga ditolak sampai snapshot koreksi yang selaras tersedia — identitas
     * unit tahun itu mengikuti snapshot beku, bukan master berjalan,
     * konsisten dengan identitas target pengukuran (`targetUnitId` memakai
     * `jadwal_snapshot.unit_id`) dan prasyarat pengajuan yang mensyaratkan
     * `rencana_aksi.unit_id` cocok dengan unit snapshot pengukuran.
     *
     * Mengembalikan snapshot terbaru agar pemanggil dapat menjepit konteks
     * awal draf.
     */
    private function pastikanSnapshotTersedia(JadwalTahunan $jadwal, IndikatorKinerja $indikator): JadwalSnapshot
    {
        $snapshot = $this->konteks->snapshotTerbaru($jadwal->id, $indikator->id, kunci: true)
            ?? throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; pembuatan draf ditolak.']);

        if ((string) $snapshot->unit_id !== (string) $indikator->unit_id) {
            throw ValidationException::withMessages(['snapshot' => 'Unit pemilik indikator telah berpindah setelah aktivasi jadwal; pembuatan draf ditolak sampai snapshot koreksi tersedia.']);
        }

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(RencanaAksi $rencanaAksi): array
    {
        return $rencanaAksi->only(['indikator_id', 'tahun', 'unit_id', 'jadwal_tahunan_id', 'snapshot_draf_id', 'penanggung_jawab_id', 'status_alur', 'versi']);
    }
}
