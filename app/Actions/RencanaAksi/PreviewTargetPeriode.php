<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\IndikatorKinerja;
use App\Models\IndikatorKomponen;
use App\Models\JadwalSnapshot;
use App\Models\JadwalSnapshotKomponen;
use App\Models\JadwalTahunan;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PreviewTargetPeriode
{
    public function __construct(
        private readonly CalculatePengukuran $calculator,
    ) {}

    /**
     * Pratinjau skor turunan tanpa persistensi (F5).
     *
     * Memakai `CalculatePengukuran` yang sama dengan jalur baca/tulis
     * sehingga React tidak menghitung formula sendiri. Nilai request
     * dilapiskan di atas target tersimpan per sel
     * (`periode_id::komponen_id`, `manual` untuk indikator manual);
     * periode efektif yang tak dikirim memakai nilai tersimpan, sehingga
     * koreksi parsial (mis. 1 dari 4) tetap menghasilkan skor,
     * peringatan turun (11.5), dan deviasi PK (11.6) yang konsisten.
     * Tidak menaikkan versi, tidak menulis baris target, tidak mencatat
     * audit — murni baca konsisten via `sharedLock`.
     *
     * Validasi bentuk ringan (duplikat, periode dikenal/anggota/efektif,
     * komponen efektif) agar pratinjau mencerminkan aturan simpan tanpa
     * menegakkan kelengkapan per-periode: sel yang belum diisi
     * menghasilkan `belum_diisi`, bukan 422. Gerbang jendela/koreksi
     * sengaja tidak ditegakkan di sini — itu kewenangan jalur simpan
     * fail-closed N1.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $id, array $data): array
    {
        $pra = RencanaAksi::findOrFail($id);
        Gate::forUser($actor)->authorize('view', $pra);
        Gate::forUser($actor)->authorize('update', $pra);

        /** @var array<string, mixed> $hasil */
        $hasil = DB::transaction(function () use ($pra, $data): array {
            $segel = RencanaAksi::whereKey($pra->getKey())->sharedLock()->firstOrFail();
            $segel->loadMissing(['indikator', 'jadwalTahunan']);
            /** @var IndikatorKinerja $indikator */
            $indikator = $segel->indikator;
            /** @var JadwalTahunan $jadwal */
            $jadwal = $segel->jadwalTahunan;

            $snapshot = $this->snapshotEfektif($jadwal, $indikator);

            // F4 (Review4 Q1): guard keselarasan unit jalur pratinjau — cermin
            // guard tulis `SimpanTargetPeriode` dan baca `IndexRencanaAksi`.
            // Ditolak fail-closed SEBELUM payload dibangun agar tak ada
            // konteks lintas-unit yang terekspos (skor/deviasi beku maupun
            // definisi komponen).
            if ($snapshot instanceof JadwalSnapshot) {
                $unitBeku = (string) ($snapshot->unit_id ?? '');
                if ($unitBeku !== '' && $unitBeku !== (string) $segel->unit_id) {
                    throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk rencana aksi ini tidak selaras; muat ulang atau hubungi perencana.']);
                }
            }

            // F2 (Review4 Q2): pratinjau terikat token — cermin guard tulis
            // `SimpanTargetPeriode`. Token halaman dibandingkan dengan snapshot
            // terbaru; usang (termasuk null eksplisit saat snapshot ada)
            // ditolak 409 agar yang ditampilkan = yang dipakai simpan.
            // Null hanya sah bila konteks memang tanpa snapshot.
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
            $definisi = $this->definisiEfektif($indikator, $snapshot, $this->jadwalPernahDiaktifkan($jadwal));

            $jendela = $this->jendelaTerurut($jadwal);
            $efektifIds = $this->periodeEfektifIds($segel, $indikator, $snapshot, $jendela);

            $tersimpan = $this->petaTarget($segel);
            $diminta = $this->normalisasiTargets($data['targets'] ?? []);
            $this->pastikanTargetsPratinjau($tipe, $definisi, $efektifIds, $jadwal, $diminta);

            $lapis = $this->petaLapis($diminta);
            $periode = $this->susunPratinjau($tipe, $presisi, $definisi, $jendela, $efektifIds, $tersimpan, $lapis);

            $alasan = array_key_exists('alasan_deviasi_pk', $data)
                ? $this->normalisasiTeks($data['alasan_deviasi_pk'])
                : $segel->alasan_deviasi_pk;

            return [
                'periode' => $periode,
                'deviasi_pk' => $this->deviasiPk($alasan, $presisi, $snapshot, $periode),
            ];
        });

        return $hasil;
    }

    private function snapshotEfektif(JadwalTahunan $jadwal, IndikatorKinerja $indikator): ?JadwalSnapshot
    {
        $snapshot = JadwalSnapshot::where('jadwal_id', $jadwal->id)
            ->where('indikator_id', $indikator->id)
            ->orderByDesc('nomor_versi')
            ->first();

        if ($snapshot === null && $this->jadwalPernahDiaktifkan($jadwal)) {
            throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia.']);
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
     * Himpunan komponen efektif read-only (cermin Index/Simpan tanpa kunci
     * baris karena pratinjau tidak menulis).
     */
    private function definisiEfektif(IndikatorKinerja $indikator, ?JadwalSnapshot $snapshot, bool $snapshotWajib): Collection
    {
        if ($snapshot instanceof JadwalSnapshot) {
            return JadwalSnapshotKomponen::where('jadwal_snapshot_id', $snapshot->id)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->get()
                ->map(fn (JadwalSnapshotKomponen $row): array => [
                    'komponen_id' => (string) $row->komponen_id,
                    'kode' => (string) $row->kode,
                    'label' => (string) $row->label,
                    'peran' => (string) $row->peran,
                    'bobot' => (string) $row->bobot,
                    'urutan' => (int) $row->urutan,
                ])
                ->values();
        }

        if ($snapshotWajib) {
            throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia.']);
        }

        return IndikatorKomponen::where('indikator_id', $indikator->id)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get()
            ->map(fn (IndikatorKomponen $row): array => [
                'komponen_id' => (string) $row->id,
                'kode' => (string) $row->kode,
                'label' => (string) $row->label,
                'peran' => (string) $row->peran,
                'bobot' => (string) $row->bobot,
                'urutan' => (int) $row->urutan,
            ])
            ->values();
    }

    /**
     * @return Collection<int, PeriodeJadwal>
     */
    private function jendelaTerurut(JadwalTahunan $jadwal): Collection
    {
        return PeriodeJadwal::where('jadwal_id', $jadwal->id)
            ->with('periode')
            ->get()
            ->sortBy(fn (PeriodeJadwal $row): string => sprintf('%010d-%s', $row->periode?->urutan ?? 0, (string) $row->periode_id))
            ->values();
    }

    /**
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @return Collection<int, string>
     */
    private function periodeEfektifIds(RencanaAksi $header, IndikatorKinerja $indikator, ?JadwalSnapshot $snapshot, Collection $jendela): Collection
    {
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
     * Target tersimpan dikelompokkan per periode lalu per komponen.
     */
    private function petaTarget(RencanaAksi $header): Collection
    {
        return RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->get()
            ->groupBy(fn (RencanaAksiTarget $row): string => (string) $row->periode_id)
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (RencanaAksiTarget $row): string => $row->komponen_id === null ? 'manual' : (string) $row->komponen_id));
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
     * Validasi bentuk pratinjau: cermin aturan simpan untuk identitas
     * (duplikat, periode dikenal/anggota/efektif, komponen efektif) tanpa
     * menuntut kelengkapan per-periode — sel hilang = belum diisi.
     *
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<int, string>  $efektifIds
     * @param  list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>  $targets
     */
    private function pastikanTargetsPratinjau(string $tipe, Collection $definisi, Collection $efektifIds, JadwalTahunan $jadwal, array $targets): void
    {
        if ($targets === []) {
            throw ValidationException::withMessages(['targets' => 'Daftar target wajib diisi.']);
        }

        $anggotaJadwal = PeriodeJadwal::where('jadwal_id', $jadwal->id)->pluck('periode_id')->map(fn ($id): string => (string) $id)->all();

        // F3 (Review4 Q2): validasi periode set-based — satu query untuk
        // seluruh ID unik agar `sharedLock` pratinjau tak tertahan oleh
        // `exists()` per-sel (s/d 600 query).
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
            if (! $efektifIds->contains($baris['periode_id'])) {
                throw ValidationException::withMessages(['targets' => 'Periode tersebut tidak berlaku untuk indikator ini.']);
            }
        }

        if ($tipe === 'manual') {
            foreach ($targets as $baris) {
                if ($baris['komponen_id'] !== null) {
                    throw ValidationException::withMessages(['targets' => 'Indikator manual memakai nilai langsung tanpa komponen.']);
                }
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
    }

    /**
     * @param  list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>  $targets
     * @return array<string, array<string, string|int|float|null>>
     */
    private function petaLapis(array $targets): array
    {
        $lapis = [];
        foreach ($targets as $baris) {
            $kunci = $baris['komponen_id'] ?? 'manual';
            $lapis[(string) $baris['periode_id']][$kunci] = $baris['nilai'];
        }

        return $lapis;
    }

    /**
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @param  Collection<int, string>  $efektifIds
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, Collection<string, RencanaAksiTarget>>  $tersimpan
     * @param  array<string, array<string, string|int|float|null>>  $lapis
     * @return list<array<string, mixed>>
     */
    private function susunPratinjau(string $tipe, int $presisi, Collection $definisi, Collection $jendela, Collection $efektifIds, Collection $tersimpan, array $lapis): array
    {
        $hasil = [];
        /** @var array<string, string|int|float|null> $nilaiSebelumnya */
        $nilaiSebelumnya = [];

        foreach ($jendela as $window) {
            $periodeId = (string) $window->periode_id;
            if (! $efektifIds->contains($periodeId)) {
                $hasil[] = [
                    'id' => $periodeId,
                    'efektif' => false,
                    'skor' => ['nilai' => null, 'status_perhitungan' => 'belum_diisi'],
                    'peringatan_turun' => false,
                    'komponen_turun' => [],
                ];

                continue;
            }

            $nilai = $this->nilaiGabungan($tipe, $definisi, $tersimpan->get($periodeId) ?? collect(), $lapis[$periodeId] ?? []);
            $skor = $this->skorPeriode($tipe, $presisi, $definisi, $nilai);
            $turun = $this->komponenTurun($nilai, $nilaiSebelumnya);
            foreach ($nilai as $kunci => $angka) {
                $nilaiSebelumnya[$kunci] = $angka;
            }

            $hasil[] = [
                'id' => $periodeId,
                'efektif' => true,
                'skor' => $skor,
                'peringatan_turun' => $turun !== [],
                'komponen_turun' => $turun,
            ];
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, RencanaAksiTarget>  $tersimpan
     * @param  array<string, string|int|float|null>  $lapis
     * @return array<string, string|int|float|null>
     */
    private function nilaiGabungan(string $tipe, Collection $definisi, Collection $tersimpan, array $lapis): array
    {
        if ($tipe === 'manual') {
            if (array_key_exists('manual', $lapis)) {
                return ['manual' => $lapis['manual']];
            }

            return ['manual' => $tersimpan->get('manual')?->nilai];
        }

        $nilai = [];
        foreach ($definisi as $item) {
            $kunci = $item['komponen_id'];
            if (array_key_exists($kunci, $lapis)) {
                $nilai[$kunci] = $lapis[$kunci];
            } else {
                $nilai[$kunci] = $tersimpan->get($kunci)?->nilai;
            }
        }

        return $nilai;
    }

    /**
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  array<string, string|int|float|null>  $nilai
     * @return array{nilai: string|null, status_perhitungan: string}
     */
    private function skorPeriode(string $tipe, int $presisi, Collection $definisi, array $nilai): array
    {
        $definitions = $definisi->map(fn (array $item): array => [
            'komponen_id' => $item['komponen_id'],
            'peran' => $item['peran'],
            'bobot' => $item['bobot'],
        ])->all();

        try {
            if ($tipe === 'manual') {
                $hitung = $this->calculator->handle($tipe, $presisi, $definitions, [], $nilai['manual'] ?? null);
            } else {
                $hitung = $this->calculator->handle($tipe, $presisi, $definitions, $nilai, null);
            }
        } catch (InvalidArgumentException|ValidationException) {
            return ['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung'];
        }

        return ['nilai' => $hitung['nilai'] === null ? null : (string) $hitung['nilai'], 'status_perhitungan' => (string) $hitung['status_perhitungan']];
    }

    /**
     * @param  array<string, string|int|float|null>  $nilai
     * @param  array<string, string|int|float|null>  $nilaiSebelumnya
     * @return list<string|null>
     */
    private function komponenTurun(array $nilai, array $nilaiSebelumnya): array
    {
        $turun = [];
        foreach ($nilai as $kunci => $sekarang) {
            $lalu = $nilaiSebelumnya[$kunci] ?? null;
            if (! is_numeric($sekarang) || ! is_numeric($lalu)) {
                continue;
            }
            if (BigDecimal::of((string) $sekarang)->isLessThan(BigDecimal::of((string) $lalu))) {
                $turun[] = $kunci === 'manual' ? null : $kunci;
            }
        }

        return $turun;
    }

    /**
     * Deviasi 11.6 atas skor pratinjau: cermin `IndexRencanaAksi::deviasiPk`
     * tetapi `alasan_terisi` memakai alasan dari request bila dikirim
     * (lapisan form reaktif), sonst nilai tersimpan. `$periode` sudah
     * terurut jendela sehingga periode efektif terakhir = elemen efektif
     * terakhir.
     *
     * @param  list<array<string, mixed>>  $periode
     * @return array{dapat_dinilai: bool, ada: bool, alasan_diperlukan: bool, alasan_terisi: bool, skor_periode_terakhir: string|null, target_pk: string|null, periode_id: string|null}
     */
    private function deviasiPk(?string $alasan, int $presisi, ?JadwalSnapshot $snapshot, array $periode): array
    {
        $efektif = array_values(array_filter($periode, fn (array $row): bool => ($row['efektif'] ?? false) === true));
        $terakhir = $efektif === [] ? null : end($efektif);
        $skor = is_array($terakhir) ? ($terakhir['skor']['nilai'] ?? null) : null;
        $targetPk = $snapshot?->target === null ? null : (string) $snapshot->target;

        $dapatDinilai = is_string($terakhir['id'] ?? null) && is_numeric($skor) && is_numeric($targetPk);
        $ada = $dapatDinilai && bccomp((string) $skor, (string) $targetPk, $presisi) !== 0;
        $alasanTerisi = trim((string) $alasan) !== '';

        return [
            'dapat_dinilai' => $dapatDinilai,
            'ada' => $ada,
            'alasan_diperlukan' => $ada,
            'alasan_terisi' => $alasanTerisi,
            'skor_periode_terakhir' => is_numeric($skor) ? (string) $skor : null,
            'target_pk' => is_numeric($targetPk) ? (string) $targetPk : null,
            'periode_id' => is_string($terakhir['id'] ?? null) ? $terakhir['id'] : null,
        ];
    }

    private function normalisasiTeks(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $teks = trim((string) $value);

        return $teks === '' ? null : $teks;
    }
}
