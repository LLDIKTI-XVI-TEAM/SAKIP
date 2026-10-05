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
use App\Services\Authorization\PermissionResolver;
use App\Support\PermissionCodes;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IndexRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly CalculatePengukuran $calculator,
    ) {}

    /**
     * Menyusun satu payload baca untuk matriks target per periode per komponen.
     *
     * Transaksi baca konsisten (T7): header dimuat ulang di dalam transaksi
     * dengan `sharedLock` (akuisisi header-dahulu, sama dengan jalur tulis
     * sehingga pembaca antre — bukan deadlock), seluruh query turunan dibaca
     * di dalam transaksi yang sama, lalu versi header diverifikasi ulang
     * setelah seluruh data dibaca. Bila versi berubah di tengah baca,
     * seluruh baca diulang hingga 3x sebelum gagal fail-closed.
     * `expected_versi` selalu berasal dari versi terverifikasi di dalam
     * transaksi, bukan dari model pra-transaksi milik controller, sehingga
     * tulis-di-tengah-baca menghasilkan payload konsisten tanpa konflik 409
     * palsu.
     *
     * Himpunan komponen efektif (D2) dan periode efektif (D3) memakai aturan
     * yang sama dengan jalur tulis `SimpanTargetPeriode`, tetapi tanpa kunci
     * baris karena jalur ini read-only. Estimasi skor dihitung server via
     * `CalculatePengukuran` agar React tidak menghitung skor turunan sendiri.
     * Peringatan turun antar-periode (11.5/PRD §14.4) dan deviasi periode
     * terakhir vs target PK (11.6/PRD §14.5) adalah data, bukan blokir —
     * penegakan alasan deviasi milik gerbang pengajuan 11.3 (ISS-05.03).
     *
     * @return array<string, mixed>
     */
    public function handle(User $actor, RencanaAksi $header): array
    {
        $percobaan = 0;

        do {
            $percobaan++;

            /** @var array{payload: array<string, mixed>, versi_awal: int, versi_akhir: int} $baca */
            $baca = DB::transaction(function () use ($actor, $header): array {
                $segel = RencanaAksi::whereKey($header->getKey())->sharedLock()->firstOrFail();
                $versiAwal = (int) $segel->versi;
                $segel->loadMissing(['indikator', 'unit', 'jadwalTahunan', 'penanggungJawab']);
                /** @var IndikatorKinerja $indikator */
                $indikator = $segel->indikator;
                /** @var JadwalTahunan $jadwal */
                $jadwal = $segel->jadwalTahunan;

                $snapshot = $this->snapshotEfektif($jadwal, $indikator);
                $tipe = $snapshot instanceof JadwalSnapshot ? (string) $snapshot->tipe_perhitungan : (string) $indikator->tipe_perhitungan;
                $presisi = (int) ($snapshot instanceof JadwalSnapshot ? $snapshot->presisi : ($indikator->presisi ?? 2));
                $definisi = $this->definisiEfektif($indikator, $snapshot, $this->jadwalPernahDiaktifkan($jadwal));

                $jendela = $this->jendelaTerurut($jadwal);
                $efektifIds = $this->periodeEfektifIds($segel, $indikator, $snapshot, $jendela);

                $baris = $this->petaTarget($segel);
                $periode = $this->susunPeriode($tipe, $presisi, $definisi, $jendela, $efektifIds, $baris);

                $unitId = (string) $segel->unit_id;

                // T10: metadata indikator (nama, satuan, arah, tipe,
                // presisi, desimal_tampilan) berasal dari jadwal_snapshot beku
                // untuk konteks RA ini, bukan master berjalan. Kode, status,
                // dan tahun_mulai_berlaku tetap milik master (tak dibekukan).
                $indikatorPayload = $indikator->only(['id', 'kode', 'nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku']);
                if ($snapshot instanceof JadwalSnapshot) {
                    $indikatorPayload['nama'] = (string) $snapshot->nama;
                    $indikatorPayload['satuan'] = (string) $snapshot->satuan;
                    $indikatorPayload['arah'] = (string) $snapshot->arah;
                    $indikatorPayload['tipe_perhitungan'] = (string) $snapshot->tipe_perhitungan;
                    $indikatorPayload['presisi'] = (int) $snapshot->presisi;
                    $indikatorPayload['desimal_tampilan'] = (int) $snapshot->desimal_tampilan;
                }

                $payload = [
                    'id' => $segel->id,
                    'tahun' => (int) $segel->tahun,
                    'status_alur' => $segel->status_alur,
                    'versi' => $versiAwal,
                    'expected_versi' => $versiAwal,
                    'uraian' => $segel->uraian,
                    'alasan_deviasi_pk' => $segel->alasan_deviasi_pk,
                    'indikator' => $indikatorPayload,
                    'unit' => $segel->unit?->only(['id', 'nama']),
                    'jadwal' => [
                        ...$jadwal->only(['id', 'tahun', 'status']),
                        'rencana_aksi_mulai' => $jadwal->rencana_aksi_mulai?->toDateString(),
                        'rencana_aksi_selesai' => $jadwal->rencana_aksi_selesai?->toDateString(),
                        'penutupan' => $jadwal->penutupan?->toDateString(),
                    ],
                    'penanggung_jawab' => $segel->penanggungJawab?->only(['id', 'nama']),
                    'tipe_perhitungan' => $tipe,
                    'presisi' => $presisi,
                    'target_pk' => $snapshot?->target,
                    'baseline' => $snapshot?->baseline,
                    'komponen' => $definisi->all(),
                    'periode' => $periode,
                    'deviasi_pk' => $this->deviasiPk($segel, $indikator, $presisi, $snapshot, $periode),
                    'can' => [
                        'view' => $this->resolver->allows($actor, PermissionCodes::RENCANA_AKSI_READ, $unitId),
                        'update' => $this->resolver->allows($actor, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId),
                    ],
                ];

                $versiAkhir = (int) RencanaAksi::whereKey($segel->getKey())->sharedLock()->value('versi');

                return ['payload' => $payload, 'versi_awal' => $versiAwal, 'versi_akhir' => $versiAkhir];
            });

            if ($baca['versi_awal'] === $baca['versi_akhir']) {
                return $baca['payload'];
            }
        } while ($percobaan < 3);

        throw ValidationException::withMessages(['rencana_aksi' => 'Data berubah saat dibaca. Muat ulang halaman sebelum menyimpan.']);
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
     * Himpunan komponen efektif selalu memakai frozen snapshot bila tersedia;
     * jadwal pernah-aktif tanpa snapshot ditolak di snapshotEfektif
     * (fail-closed), bukan fallback ke master live.
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
     * Himpunan periode efektif memakai jendela jadwal minus periode
     * pra-berlaku (cermin D3 jalur tulis: Tidak berlaku, bukan nol/null).
     *
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
     * Target tersimpan dikelompokkan per periode lalu per komponen
     * (`manual` untuk baris manual `komponen_id = NULL`).
     */
    private function petaTarget(RencanaAksi $header): Collection
    {
        return RencanaAksiTarget::where('rencana_aksi_id', $header->id)
            ->get()
            ->groupBy(fn (RencanaAksiTarget $row): string => (string) $row->periode_id)
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (RencanaAksiTarget $row): string => $row->komponen_id === null ? 'manual' : (string) $row->komponen_id));
    }

    /**
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @param  Collection<int, string>  $efektifIds
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, Collection<string, RencanaAksiTarget>>  $baris
     * @return list<array<string, mixed>>
     */
    private function susunPeriode(string $tipe, int $presisi, Collection $definisi, Collection $jendela, Collection $efektifIds, Collection $baris): array
    {
        $hasil = [];
        /** @var array<string, string|null> $nilaiSebelumnya nilai komponen periode efektif sebelumnya */
        $nilaiSebelumnya = [];

        foreach ($jendela as $window) {
            $periodeId = (string) $window->periode_id;
            $efektif = $efektifIds->contains($periodeId);
            if (! $efektif) {
                $hasil[] = $this->barisTidakBerlaku($window);

                continue;
            }

            $nilai = $this->nilaiPeriode($tipe, $definisi, $baris->get($periodeId) ?? collect());
            $skor = $this->skorPeriode($tipe, $presisi, $definisi, $nilai);
            $turun = $this->komponenTurun($nilai, $nilaiSebelumnya);
            foreach ($nilai as $kunci => $angka) {
                $nilaiSebelumnya[$kunci] = $angka;
            }

            $hasil[] = [
                'id' => $periodeId,
                'nama' => $window->periode?->nama,
                'urutan' => (int) ($window->periode?->urutan ?? 0),
                'efektif' => true,
                'status' => 'efektif',
                'nilai' => $this->tampilNilai($tipe, $definisi, $baris->get($periodeId) ?? collect()),
                'skor' => $skor,
                'peringatan_turun' => $turun !== [],
                'komponen_turun' => $turun,
            ];
        }

        return $hasil;
    }

    /**
     * @return array<string, mixed>
     */
    private function barisTidakBerlaku(PeriodeJadwal $window): array
    {
        return [
            'id' => (string) $window->periode_id,
            'nama' => $window->periode?->nama,
            'urutan' => (int) ($window->periode?->urutan ?? 0),
            'efektif' => false,
            'status' => 'tidak_berlaku',
            'nilai' => [],
            'skor' => null,
            'peringatan_turun' => false,
            'komponen_turun' => [],
        ];
    }

    /**
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, RencanaAksiTarget>  $tersimpan
     * @return array<string, string|int|float|null>
     */
    private function nilaiPeriode(string $tipe, Collection $definisi, Collection $tersimpan): array
    {
        if ($tipe === 'manual') {
            return ['manual' => $tersimpan->get('manual')?->nilai];
        }

        $nilai = [];
        foreach ($definisi as $item) {
            $nilai[$item['komponen_id']] = $tersimpan->get($item['komponen_id'])?->nilai;
        }

        return $nilai;
    }

    /**
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, RencanaAksiTarget>  $tersimpan
     * @return list<array{komponen_id: string|null, kode: string|null, label: string|null, nilai: string|null, keterangan: string|null}>
     */
    private function tampilNilai(string $tipe, Collection $definisi, Collection $tersimpan): array
    {
        if ($tipe === 'manual') {
            $row = $tersimpan->get('manual');

            return [[
                'komponen_id' => null,
                'kode' => null,
                'label' => null,
                'nilai' => $row?->nilai === null ? null : (string) $row->nilai,
                'keterangan' => $row?->keterangan,
            ]];
        }

        return $definisi->map(function (array $item) use ($tersimpan): array {
            $row = $tersimpan->get($item['komponen_id']);

            return [
                'komponen_id' => $item['komponen_id'],
                'kode' => $item['kode'],
                'label' => $item['label'],
                'nilai' => $row?->nilai === null ? null : (string) $row->nilai,
                'keterangan' => $row?->keterangan,
            ];
        })->all();
    }

    /**
     * Estimasi skor dihitung server; kegagalan hitung tidak menggagalkan
     * halaman baca (data drift pasca-simpan tetap tampil sebagai data).
     *
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
                $hitung = $this->calculator->handle($tipe, $presisi, [], [], $nilai['manual'] ?? null);
            } else {
                $hitung = $this->calculator->handle($tipe, $presisi, $definitions, $nilai, null);
            }
        } catch (InvalidArgumentException|ValidationException) {
            return ['nilai' => null, 'status_perhitungan' => 'tidak_dapat_dihitung'];
        }

        return ['nilai' => $hitung['nilai'] === null ? null : (string) $hitung['nilai'], 'status_perhitungan' => (string) $hitung['status_perhitungan']];
    }

    /**
     * Peringatan kumulatif 11.5: nilai komponen lebih kecil dari periode
     * efektif sebelumnya pada komponen yang sama. Hanya bila kedua nilai
     * terisi angka (`null` = belum diisi, bukan nol) — tanpa memblokir.
     *
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
     * Deviasi 11.6: skor turunan periode efektif terakhir vs target PK
     * (salinan beku `jadwal_snapshot.target`, konsisten AC-6 ISS "target PK
     * snapshot") dengan toleransi `presisi`. Bila tak dapat dinilai
     * (skor/target tak tersedia), alasan tidak diperlukan.
     *
     * @param  list<array<string, mixed>>  $periode
     * @return array{dapat_dinilai: bool, ada: bool, alasan_diperlukan: bool, alasan_terisi: bool, skor_periode_terakhir: string|null, target_pk: string|null, periode_id: string|null}
     */
    private function deviasiPk(RencanaAksi $header, IndikatorKinerja $indikator, int $presisi, ?JadwalSnapshot $snapshot, array $periode): array
    {
        $terakhir = collect($periode)->where('efektif', true)->sortBy('urutan')->last();
        $skor = is_array($terakhir) ? ($terakhir['skor']['nilai'] ?? null) : null;
        $targetPk = $snapshot?->target === null ? null : (string) $snapshot->target;

        $dapatDinilai = is_string($terakhir['id'] ?? null) && is_numeric($skor) && is_numeric($targetPk);
        $ada = $dapatDinilai && bccomp((string) $skor, (string) $targetPk, $presisi) !== 0;
        $alasanTerisi = trim((string) $header->alasan_deviasi_pk) !== '';

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
}
