<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Services\RencanaAksi\RekonsiliasiTargetDraf;
use App\Support\PermissionCodes;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IndexRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly CalculatePengukuran $calculator,
        private readonly RekonsiliasiTargetDraf $rekonsiliasi,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly KonteksBekuRencanaAksi $konteks,
    ) {}

    /**
     * Menyusun satu payload baca untuk matriks target per periode per komponen.
     *
     * Header dimuat di luar transaksi hanya untuk 404 dan otorisasi baca
     * (`unit_id` imutabel pasca-create sehingga aman); lookup mendahului
     * Gate agar UUID asing selalu 404.
     *
     * Transaksi baca konsisten: header dimuat ulang di dalam transaksi
     * dengan `sharedLock` (akuisisi header-dahulu, sama dengan jalur tulis
     * sehingga pembaca antre — bukan deadlock) dan seluruh query turunan
     * dibaca di dalam transaksi yang sama. Penulis target mengunci header
     * `FOR UPDATE`, yang berkonflik dengan kunci baca ini, sehingga versi dan
     * target tidak dapat berubah sampai baca selesai. `expected_versi`
     * berasal dari header terkunci, bukan dari model pra-transaksi milik
     * controller.
     *
     * Payload juga membawa token konkurensi snapshot
     * (`expected_snapshot_id` + `expected_snapshot_versi` dari
     * `jadwal_snapshot.id`/`nomor_versi` terbaru) agar React
     * mengembalikannya saat simpan; jalur tulis menolak 409 bila snapshot
     * terbaru berubah sejak payload dibaca. Header tanpa snapshot (jadwal
     * belum pernah aktif) ditolak fail-closed, bukan dibaca dari master.
     *
     * Himpunan komponen efektif dan periode efektif memakai aturan
     * yang sama dengan jalur tulis `SimpanTargetPeriode`, tetapi tanpa kunci
     * baris karena jalur ini read-only. Estimasi skor dihitung server via
     * `CalculatePengukuran` agar React tidak menghitung skor turunan sendiri.
     * Peringatan turun antar-periode (PRD §14.4) dan deviasi periode
     * terakhir vs target PK (PRD §14.5) adalah data, bukan blokir —
     * penegakan alasan deviasi milik gerbang pengajuan.
     *
     * @return array<string, mixed>
     */
    public function handle(User $actor, string $id): array
    {
        $header = RencanaAksi::findOrFail($id);
        Gate::forUser($actor)->authorize('view', $header);

        return DB::transaction(function () use ($actor, $header): array {
            $segel = RencanaAksi::whereKey($header->getKey())->sharedLock()->firstOrFail();
            $segel->loadMissing(['indikator', 'unit', 'jadwalTahunan', 'penanggungJawab']);
            /** @var IndikatorKinerja $indikator */
            $indikator = $segel->indikator;
            /** @var JadwalTahunan $jadwal */
            $jadwal = $segel->jadwalTahunan;

            $snapshot = $this->konteks->snapshotTerbaru($jadwal->id, $indikator->id)
                ?? throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia.']);

            // Guard keselarasan unit jalur baca — cermin
            // guard tulis `SimpanTargetPeriode`.
            // Auth baca dievaluasi terhadap header.unit_id, sementara
            // konteks efektif berasal dari snapshot terbaru; bila snapshot
            // milik unit B untuk header milik unit A (indikator pindah
            // unit pasca-aktivasi), tolak fail-closed SEBELUM payload
            // dibangun agar tak ada konteks lintas-unit yang terekspos
            // (nama/satuan/target beku, komponen, maupun periode).
            if ((string) $snapshot->unit_id !== (string) $segel->unit_id) {
                throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk rencana aksi ini tidak selaras; muat ulang atau hubungi perencana.']);
            }

            $tipe = (string) $snapshot->tipe_perhitungan;
            $presisi = (int) $snapshot->presisi;
            $definisi = $this->konteks->komponen($snapshot);

            $jendela = $this->konteks->jendela($jadwal->id);
            $efektifIds = $this->konteks->periodeEfektif($snapshot, $jendela);

            $baris = $this->petaTarget($segel);

            // Baris basi transisi (tak efektif pada
            // satu pun versi antara jepit→terbaru) disaring bagai tak
            // ada — sel tampil kosong. Tanpa efek samping: penghapusan
            // + audit milik jalur tulis berikutnya.
            $baris = $this->rekonsiliasi->saringPetaBasi($baris, $this->rekonsiliasi->rekonsiliasi($segel, $snapshot)['kunci']);

            $periode = $this->susunPeriode($tipe, $presisi, $definisi, $jendela, $efektifIds, $baris);
            $koreksi = $this->statusKoreksi($jadwal, $indikator);

            $unitId = (string) $segel->unit_id;
            $keputusanUbah = $this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId);

            // Metadata indikator (nama, satuan, arah, tipe,
            // presisi, desimal_tampilan) berasal dari jadwal_snapshot beku
            // untuk konteks RA ini, bukan master berjalan. Kode, status,
            // dan tahun_mulai_berlaku tetap milik master (tak dibekukan).
            $indikatorPayload = $indikator->only(['id', 'kode', 'nama', 'satuan', 'arah', 'tipe_perhitungan', 'presisi', 'desimal_tampilan', 'status', 'tahun_mulai_berlaku']);
            $indikatorPayload['nama'] = (string) $snapshot->nama;
            $indikatorPayload['satuan'] = (string) $snapshot->satuan;
            $indikatorPayload['arah'] = (string) $snapshot->arah;
            $indikatorPayload['tipe_perhitungan'] = (string) $snapshot->tipe_perhitungan;
            $indikatorPayload['presisi'] = (int) $snapshot->presisi;
            $indikatorPayload['desimal_tampilan'] = (int) $snapshot->desimal_tampilan;

            return [
                'id' => $segel->id,
                'tahun' => (int) $segel->tahun,
                'status_alur' => $segel->status_alur,
                'versi' => (int) $segel->versi,
                'expected_versi' => (int) $segel->versi,
                // Token konkurensi snapshot (identitas + nomor versi
                // beku terbaru). React mengembalikan keduanya apa adanya;
                // tanpa logika formula di klien.
                'expected_snapshot_id' => (string) $snapshot->id,
                'expected_snapshot_versi' => (int) $snapshot->nomor_versi,
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
                'target_pk' => $snapshot->target,
                'baseline' => $snapshot->baseline,
                'komponen' => $definisi->all(),
                'periode' => $periode,
                // Lingkup koreksi untuk UI; lihat statusKoreksi.
                'koreksi' => $koreksi,
                'deviasi_pk' => $this->deviasiPk($segel, $presisi, $snapshot, $periode),
                // `update` memakai syarat gerbang tulis `SimpanTargetPeriode`
                // (izin, unit aktif, indikator bukan arsip, status draf,
                // lingkup koreksi yang mencakup periode efektif, jendela
                // tulis) agar formulir tidak dibuka untuk penyimpanan yang
                // pasti ditolak.
                'can' => [
                    'view' => $this->resolver->allows($actor, PermissionCodes::RENCANA_AKSI_READ, $unitId),
                    'update' => $keputusanUbah->allowed
                        && $segel->unit?->status === 'aktif'
                        && ! $indikator->isArsip()
                        && in_array($segel->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)
                        && (! $koreksi['aktif'] || array_intersect($efektifIds->all(), $koreksi['periode_ids']) !== [])
                        && $this->jendela->alasanTolak($actor, $keputusanUbah, $indikator, $jadwal, 'penyimpanan') === null,
                ],
            ];
        });
    }

    /**
     * Status sesi koreksi untuk UI: `aktif` true hanya bila penutupan
     * terlewati (zona bisnis) dan sesi koreksi sah menurut
     * `JendelaTulisRencanaAksi`, sumber aturan yang sama dengan gerbang
     * tulis. UI memakai ini untuk menonaktifkan periode di luar lingkup;
     * backend tetap menolak fail-closed bila klien nakal mengirimnya.
     *
     * @return array{aktif: bool, periode_ids: list<string>}
     */
    private function statusKoreksi(JadwalTahunan $jadwal, IndikatorKinerja $indikator): array
    {
        return [
            'aktif' => $this->jendela->tahunDitutup($jadwal) && $this->jendela->sesiKoreksiAktif($indikator, $jadwal),
            'periode_ids' => $this->jendela->periodeLingkupKoreksi($jadwal),
        ];
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
     * Peringatan kumulatif: nilai komponen lebih kecil dari periode
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
     * Deviasi: skor turunan periode efektif terakhir vs target PK
     * (salinan beku `jadwal_snapshot.target`, yaitu target PK snapshot)
     * dengan toleransi `presisi`. Bila tak dapat dinilai
     * (skor/target tak tersedia), alasan tidak diperlukan.
     *
     * @param  list<array<string, mixed>>  $periode
     * @return array{dapat_dinilai: bool, ada: bool, alasan_diperlukan: bool, alasan_terisi: bool, skor_periode_terakhir: string|null, target_pk: string|null, periode_id: string|null}
     */
    private function deviasiPk(RencanaAksi $header, int $presisi, JadwalSnapshot $snapshot, array $periode): array
    {
        $terakhir = collect($periode)->where('efektif', true)->sortBy('urutan')->last();
        $skor = is_array($terakhir) ? ($terakhir['skor']['nilai'] ?? null) : null;
        $targetPk = $snapshot->target === null ? null : (string) $snapshot->target;

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
