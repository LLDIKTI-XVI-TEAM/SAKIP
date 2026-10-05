<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Authorization\PermissionResolver;
use App\Services\Authorization\ResolveLockedActor;
use App\Services\Perencanaan\IndikatorArsipGuard;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SimpanTargetPeriode
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly ResolveLockedActor $lockedActor,
        private readonly IndikatorArsipGuard $arsipGuard,
        private readonly CalculatePengukuran $calculator,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Menyimpan target per periode per komponen efektif di atas header draf.
     *
     * Urutan kunci deterministik di dalam transaksi (anti-deadlock, sama
     * dengan `EnsureDraftRencanaAksi`): pengguna beserta baris ACL, header
     * rencana aksi, indikator, jadwal tahunan, snapshot beserta
     * komponennya, jendela periode jadwal, lalu baris target. Tanpa retry:
     * antrean kunci menserialkan transaksi bersamaan, bukan 40P01. Bacaan
     * tanpa kunci (`Periode::exists`, PIC efektif jalur tulis) tidak ikut
     * urutan karena tidak menahan kunci baris.
     *
     * Izin dievaluasi
     * ulang memakai state terkunci, jendela PIC/Perencanaan diperiksa memakai
     * tanggal Asia/Makassar, nilai turunan dihitung server tanpa disimpan,
     * dan versi bertambah satu dengan penolakan stale memakai status 409
     * (F4: token `expected_snapshot_id`/`expected_snapshot_versi` ikut
     * ditolak 409 bila snapshot terbaru berubah sejak payload dibaca).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, string $id, array $data): RencanaAksi
    {
        /** @var array<string, mixed>|null $dasarIzin */
        $dasarIzin = null;

        try {
            return DB::transaction(function () use ($actor, $id, $data, &$dasarIzin) {
                $kunci = $this->lockedActor->handle($actor, PermissionCodes::RENCANA_AKSI_UPDATE);
                /** @var User|null $pengunci */
                $pengunci = $kunci['aktor'];
                if (! $pengunci instanceof User || $pengunci->status !== 'aktif') {
                    $dasarIzin = $kunci['keputusan']->toAuditBasis();
                    throw new AuthorizationException('Akun pengguna tidak aktif.');
                }

                $header = RencanaAksi::lockForUpdate()->findOrFail($id);
                $indikator = IndikatorKinerja::lockForUpdate()->findOrFail($header->indikator_id);
                $jadwal = JadwalTahunan::lockForUpdate()->findOrFail($header->jadwal_tahunan_id);

                $unitHeader = Unit::whereKey((string) $header->unit_id)->sharedLock()->first();
                if (! $unitHeader instanceof Unit || $unitHeader->status !== 'aktif') {
                    throw ValidationException::withMessages(['unit_id' => 'Unit pemilik rencana aksi berstatus nonaktif.']);
                }

                $keputusan = $this->resolver->decide($pengunci, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $header->unit_id);
                $dasarIzin = $keputusan;
                if (! $keputusan['allowed']) {
                    throw new AuthorizationException('Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.');
                }

                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                if (! in_array($header->status_alur, [RencanaAksi::STATUS_DRAFT, RencanaAksi::STATUS_DIKEMBALIKAN], true)) {
                    throw ValidationException::withMessages(['status_alur' => 'Target hanya dapat disimpan pada draf yang dapat disunting.']);
                }

                $expected = (int) ($data['expected_versi'] ?? 0);
                if ($header->versi !== $expected) {
                    throw ValidationException::withMessages(['expected_versi' => 'Data telah berubah. Muat ulang sebelum mengulangi penyimpanan.'])->status(409);
                }

                $snapshotWajib = $this->jadwalPernahDiaktifkan($jadwal);
                $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal->id)
                    ->where('indikator_id', $indikator->id)
                    ->orderByDesc('nomor_versi')
                    ->lockForUpdate()
                    ->first();
                if ($snapshotWajib && ! $snapshot instanceof JadwalSnapshot) {
                    throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; penyimpanan ditolak.']);
                }

                // F1: guard keselarasan unit jalur update (lanjutan T9/N1 yang
                // hanya di create). Auth dievaluasi terhadap header.unit_id,
                // sementara konteks efektif berasal dari snapshot terbaru —
                // bila keduanya berbeda, tolak fail-closed agar RA tidak lolos
                // tulis di sini lalu ditolak SubmissionPrerequisites.
                if ($snapshot instanceof JadwalSnapshot) {
                    $unitBeku = (string) ($snapshot->unit_id ?? '');
                    if ($unitBeku !== '' && $unitBeku !== (string) $header->unit_id) {
                        throw ValidationException::withMessages(['snapshot' => 'Unit pemilik rencana aksi tidak selaras dengan konteks beku terbaru; penyimpanan ditolak sampai snapshot koreksi tersedia.']);
                    }
                }

                // F1 (Review4 Q1) + F4: token konkurensi snapshot (eksplisit,
                // tanpa bump semu versi header) WAJIB pada setiap penyimpanan.
                // Snapshot koreksi baru yang terbit antara baca-simpan mengubah
                // konteks diam-diam (tipe/bobot/presisi/periode-mulai)
                // sementara ID komponen sama — simpan dengan token lama/usang
                // (termasuk kunci hilang yang dinormalisasi menjadi null, atau
                // null eksplisit saat snapshot ada) ditolak 409 agar nilai tak
                // diterima dengan konteks yang tak pernah dilihat pengguna.
                // Null hanya sah bila konteks memang tanpa snapshot (jadwal
                // belum pernah aktif → snapshot null). Tanpa jalur bypass:
                // perbandingan selalu dijalankan, bukan hanya bila kunci ada.
                $tokenId = $data['expected_snapshot_id'] ?? null;
                $tokenVersi = $data['expected_snapshot_versi'] ?? null;
                $tokenId = $tokenId === null ? null : (string) $tokenId;
                $tokenVersi = $tokenVersi === null ? null : (int) $tokenVersi;
                $aktualId = $snapshot instanceof JadwalSnapshot ? (string) $snapshot->id : null;
                $aktualVersi = $snapshot instanceof JadwalSnapshot ? (int) $snapshot->nomor_versi : null;
                if ($tokenId !== $aktualId || $tokenVersi !== $aktualVersi) {
                    throw ValidationException::withMessages(['expected_snapshot_id' => 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang sebelum mengulangi penyimpanan.'])->status(409);
                }

                $tipe = $snapshot instanceof JadwalSnapshot ? (string) $snapshot->tipe_perhitungan : (string) $indikator->tipe_perhitungan;
                $presisi = (int) ($snapshot instanceof JadwalSnapshot ? $snapshot->presisi : ($indikator->presisi ?? 2));
                $definisi = $this->definisiEfektif($indikator, $snapshot, $snapshotWajib);
                $periodeEfektif = $this->periodeEfektif($header, $indikator, $jadwal, $snapshot);
                $anggotaJadwal = PeriodeJadwal::where('jadwal_id', $jadwal->id)->pluck('periode_id')->map(fn ($id): string => (string) $id)->all();

                $targets = $this->normalisasiTargets($data['targets'] ?? []);
                $this->pastikanJendela($pengunci, $keputusan, $indikator, $jadwal, $targets);
                $this->pastikanTargetsSah($tipe, $presisi, $definisi, $periodeEfektif, $anggotaJadwal, $targets);

                $sebelum = $this->auditState($header);
                $header->fill([
                    'uraian' => array_key_exists('uraian', $data) ? $this->normalisasiTeks($data['uraian']) : $header->uraian,
                    'alasan_deviasi_pk' => array_key_exists('alasan_deviasi_pk', $data) ? $this->normalisasiTeks($data['alasan_deviasi_pk']) : $header->alasan_deviasi_pk,
                ]);
                $header->versi++;
                $header->save();

                RencanaAksiTarget::where('rencana_aksi_id', $header->id)->lockForUpdate()->get();
                foreach ($targets as $baris) {
                    $this->simpanBaris($header->id, $pengunci->id, $baris);
                }

                $sesudah = $this->auditState($header->fresh());
                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'rencana_aksi.ubah',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $header->id,
                    nilaiLama: $sebelum,
                    nilaiBaru: $sesudah,
                    alasan: 'Menyimpan target rencana aksi per periode.',
                    dasarIzin: $dasarIzin,
                );

                return $header->fresh();
            });
        } catch (Throwable $exception) {
            if (($exception instanceof AuthorizationException || $exception instanceof ValidationException)
                && RencanaAksi::whereKey($id)->exists()) {
                $this->audit->catat(
                    actor: $actor,
                    tindakan: 'rencana_aksi.ubah_ditolak',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $id,
                    alasan: AlasanAudit::sanitasi($exception->getMessage(), 'Penyimpanan target rencana aksi ditolak.'),
                    dasarIzin: is_array($dasarIzin) ? $dasarIzin : null,
                );
            }

            throw $exception;
        }
    }

    /**
     * Jadwal dianggap pernah diaktifkan bila terkunci (aktif/ditutup atau
     * activated_at terisi). RA yang terikat padanya wajib memakai frozen
     * snapshot versi resmi terbaru, bukan live master.
     */
    private function jadwalPernahDiaktifkan(JadwalTahunan $jadwal): bool
    {
        return $jadwal->is_terkunci
            || $jadwal->activated_at !== null
            || in_array($jadwal->status, ['aktif', 'ditutup'], true);
    }

    /**
     * Himpunan komponen efektif selalu memakai frozen snapshot bila RA terikat
     * pada jadwal yang pernah diaktifkan; snapshot wajib-tapi-hilang ditolak
     * di pemanggil (fail-closed), bukan fallback ke master live.
     */
    private function definisiEfektif(IndikatorKinerja $indikator, ?JadwalSnapshot $snapshot, bool $snapshotWajib): Collection
    {
        if ($snapshot instanceof JadwalSnapshot) {
            $rows = JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshot->id)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->lockForUpdate()
                ->get();

            return $rows->map(fn (JadwalSnapshotKomponen $row): array => [
                'komponen_id' => (string) $row->komponen_id,
                'kode' => (string) $row->kode,
                'label' => (string) $row->label,
                'peran' => (string) $row->peran,
                'bobot' => (string) $row->bobot,
                'urutan' => (int) $row->urutan,
            ])->values();
        }

        if ($snapshotWajib) {
            throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; penyimpanan ditolak.']);
        }

        $rows = IndikatorKomponen::where('indikator_id', $indikator->id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('kode')
            ->lockForUpdate()
            ->get();

        return $rows->map(fn (IndikatorKomponen $row): array => [
            'komponen_id' => (string) $row->id,
            'kode' => (string) $row->kode,
            'label' => (string) $row->label,
            'peran' => (string) $row->peran,
            'bobot' => (string) $row->bobot,
            'urutan' => (int) $row->urutan,
        ])->values();
    }

    /**
     * Himpunan periode efektif memakai jendela jadwal minus periode pra-berlaku.
     */
    private function periodeEfektif(RencanaAksi $header, IndikatorKinerja $indikator, JadwalTahunan $jadwal, ?JadwalSnapshot $snapshot): Collection
    {
        $jendela = PeriodeJadwal::where('jadwal_id', $jadwal->id)
            ->with('periode')
            ->lockForUpdate()
            ->get()
            ->sortBy(fn (PeriodeJadwal $row): int => $row->periode?->urutan ?? 0)
            ->values();

        if ((int) $indikator->tahun_mulai_berlaku > (int) $header->tahun) {
            return collect();
        }

        if ($snapshot instanceof JadwalSnapshot && is_string($snapshot->periode_mulai_id)) {
            $mulai = Periode::whereKey($snapshot->periode_mulai_id)->first();
            if ($mulai instanceof Periode) {
                return $jendela
                    ->filter(fn (PeriodeJadwal $row): bool => ($row->periode?->urutan ?? 0) >= $mulai->urutan)
                    ->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)
                    ->values();
            }
        }

        return $jendela->map(fn (PeriodeJadwal $row): string => (string) $row->periode_id)->values();
    }

    /**
     * @return list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>
     */
    private function normalisasiTargets(mixed $targets): array
    {
        if (! is_array($targets)) {
            throw ValidationException::withMessages(['targets' => 'Daftar target tidak sah.']);
        }

        $hasil = [];
        foreach (array_values($targets) as $baris) {
            if (! is_array($baris)) {
                throw ValidationException::withMessages(['targets' => 'Daftar target tidak sah.']);
            }
            $hasil[] = [
                'periode_id' => (string) ($baris['periode_id'] ?? ''),
                'komponen_id' => array_key_exists('komponen_id', $baris) && $baris['komponen_id'] !== null ? (string) $baris['komponen_id'] : null,
                'nilai' => array_key_exists('nilai', $baris) ? $baris['nilai'] : null,
                'keterangan' => array_key_exists('keterangan', $baris) && $baris['keterangan'] !== null ? trim((string) $baris['keterangan']) : null,
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $keputusan
     * @param  list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>  $targets
     */
    private function pastikanJendela(User $pengunci, array $keputusan, IndikatorKinerja $indikator, JadwalTahunan $jadwal, array $targets): void
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $penutupan = $jadwal->penutupan?->toDateString();
        if (is_string($penutupan) && $hariIni > $penutupan && ! $this->dalamKoreksiSah($indikator, $jadwal, $targets)) {
            throw ValidationException::withMessages(['jendela' => 'Tahun jadwal telah ditutup; penyimpanan memerlukan sesi koreksi resmi.']);
        }

        if ($this->jalurPerencanaan($pengunci, $keputusan)) {
            return;
        }

        $pic = PenugasanIndikator::where('indikator_id', $indikator->id)
            ->whereDate('tanggal_mulai_berlaku', '<=', $hariIni)
            ->orderByDesc('tanggal_mulai_berlaku')
            ->orderByDesc('created_at')
            ->first();
        if (! $pic instanceof PenugasanIndikator || (string) $pic->user_id !== (string) $pengunci->id) {
            throw ValidationException::withMessages(['jendela' => 'Tindakan ini memerlukan penugasan PIC yang efektif.']);
        }

        $mulai = $jadwal->rencana_aksi_mulai?->toDateString();
        $selesai = $jadwal->rencana_aksi_selesai?->toDateString();
        if (! is_string($mulai) || ! is_string($selesai) || $hariIni < $mulai || $hariIni > $selesai) {
            throw ValidationException::withMessages(['jendela' => 'Jendela penyusunan rencana aksi periode ini sudah ditutup.']);
        }
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

    /**
     * Sesi koreksi sah bila jendela waktu berjalan dan cakupan jenis objek
     * serta indikator cocok. Setiap periode_id dalam REQUEST wajib termasuk
     * dalam lingkup_koreksi.periode_ids (bila kunci itu ada) — acuan validasi
     * adalah periode yang diminta, bukan yang tersimpan, sehingga header
     * tanpa target lama pun tetap divalidasi.
     *
     * @param  list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>  $targets
     */
    private function dalamKoreksiSah(IndikatorKinerja $indikator, JadwalTahunan $jadwal, array $targets): bool
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
        $cakupanPeriode = $lingkup['periode_ids'] ?? null;
        if (is_array($cakupanPeriode)) {
            $diizinkan = array_map(fn ($id): string => (string) $id, $cakupanPeriode);
            foreach ($targets as $baris) {
                $periodeId = (string) ($baris['periode_id'] ?? '');
                if ($periodeId === '' || ! in_array($periodeId, $diizinkan, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function pastikanTargetsSah(string $tipe, int $presisi, Collection $definisi, Collection $periodeEfektif, array $anggotaJadwal, array $targets): void
    {
        if ($targets === []) {
            throw ValidationException::withMessages(['targets' => 'Daftar target wajib diisi.']);
        }

        // F3 (Review4 Q2): validasi periode set-based — satu query untuk
        // seluruh ID unik, bukan `exists()` per-sel (s/d 600 query) yang
        // menahan lock transaksi lebih lama dari perlu.
        $periodeIds = collect($targets)->pluck('periode_id')->map(fn ($id): string => (string) $id)->unique()->values()->all();
        $dikenal = Periode::whereIn('id', $periodeIds)->pluck('id')->map(fn ($id): string => (string) $id)->flip()->all();

        $kunci = [];
        foreach ($targets as $baris) {
            $pasangan = $baris['periode_id'].'::'.($baris['komponen_id'] ?? 'null');
            if (array_key_exists($pasangan, $kunci)) {
                throw ValidationException::withMessages(['targets' => 'Target tidak boleh dikirim berulang untuk kombinasi periode dan komponen yang sama.']);
            }
            $kunci[$pasangan] = true;

            if (! array_key_exists($baris['periode_id'], $dikenal)) {
                throw ValidationException::withMessages(['targets' => 'Periode target tidak dikenal.']);
            }
            if (! in_array($baris['periode_id'], $anggotaJadwal, true)) {
                throw ValidationException::withMessages(['targets' => 'Periode bukan anggota jadwal tahun ini.']);
            }
            if (! $periodeEfektif->contains($baris['periode_id'])) {
                throw ValidationException::withMessages(['targets' => 'Periode tersebut tidak berlaku untuk indikator ini.']);
            }
        }

        if ($tipe === 'manual') {
            foreach ($targets as $baris) {
                if ($baris['komponen_id'] !== null) {
                    throw ValidationException::withMessages(['targets' => 'Indikator manual memakai nilai langsung tanpa komponen.']);
                }
            }
            $perPeriode = collect($targets)->groupBy('periode_id');
            foreach ($perPeriode as $rows) {
                if ($rows->count() !== 1) {
                    throw ValidationException::withMessages(['targets' => 'Indikator manual wajib tepat satu baris per periode.']);
                }
            }
            foreach ($perPeriode as $rows) {
                $this->hitungTurunan($tipe, $presisi, $definisi, $rows->all());
            }

            return;
        }

        $wajib = $definisi->pluck('komponen_id')->sort()->values()->all();
        if ($wajib === []) {
            throw ValidationException::withMessages(['targets' => 'Definisi komponen efektif belum tersedia untuk indikator nonmanual.']);
        }
        foreach ($targets as $baris) {
            if ($baris['komponen_id'] === null || ! in_array($baris['komponen_id'], $wajib, true)) {
                throw ValidationException::withMessages(['targets' => 'Komponen bukan anggota himpunan efektif indikator ini.']);
            }
        }
        $perPeriode = collect($targets)->groupBy('periode_id');
        foreach ($perPeriode as $rows) {
            $kirim = $rows->pluck('komponen_id')->sort()->values()->all();
            if ($kirim !== $wajib) {
                throw ValidationException::withMessages(['targets' => 'Seluruh komponen efektif harus dikirim per periode; gunakan nilai kosong untuk yang belum diisi.']);
            }
            $this->hitungTurunan($tipe, $presisi, $definisi, $rows->all());
        }
    }

    /**
     * Menghitung skor turunan server untuk validasi tanpa menyimpannya.
     */
    private function hitungTurunan(string $tipe, int $presisi, Collection $definisi, array $rows): void
    {
        $definitions = $definisi->all();
        if ($tipe === 'manual') {
            $manual = $rows[0]['nilai'] ?? null;
            try {
                $this->calculator->handle($tipe, $presisi, $definitions, [], $manual);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['targets' => $exception->getMessage()]);
            }

            return;
        }

        $values = [];
        foreach ($rows as $row) {
            $values[(string) $row['komponen_id']] = $row['nilai'];
        }
        try {
            $this->calculator->handle($tipe, $presisi, $definitions, $values, null);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['targets' => $exception->getMessage()]);
        }
    }

    private function simpanBaris(string $rencanaAksiId, string $actorId, array $baris): void
    {
        $query = RencanaAksiTarget::where('rencana_aksi_id', $rencanaAksiId)->where('periode_id', $baris['periode_id']);
        if ($baris['komponen_id'] === null) {
            $query->whereNull('komponen_id');
        } else {
            $query->where('komponen_id', $baris['komponen_id']);
        }
        $existing = $query->lockForUpdate()->first();
        if ($existing instanceof RencanaAksiTarget) {
            $existing->fill(['nilai' => $baris['nilai'], 'keterangan' => $baris['keterangan'], 'updated_by' => $actorId, 'updated_at' => now()]);
            $existing->save();

            return;
        }

        RencanaAksiTarget::create([
            'rencana_aksi_id' => $rencanaAksiId,
            'periode_id' => $baris['periode_id'],
            'komponen_id' => $baris['komponen_id'],
            'nilai' => $baris['nilai'],
            'keterangan' => $baris['keterangan'],
            'updated_by' => $actorId,
            'updated_at' => now(),
        ]);
    }

    private function normalisasiTeks(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $teks = trim((string) $value);

        return $teks === '' ? null : $teks;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditState(RencanaAksi $header): array
    {
        $targets = RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->orderBy('periode_id')
            ->orderBy('komponen_id')
            ->get(['periode_id', 'komponen_id', 'nilai', 'keterangan'])
            ->toArray();

        return [...$header->only(['status_alur', 'versi', 'uraian', 'alasan_deviasi_pk']), 'targets' => $targets];
    }
}
