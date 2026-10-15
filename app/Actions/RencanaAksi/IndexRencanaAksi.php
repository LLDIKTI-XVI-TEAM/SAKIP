<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\EvaluateEvidence;
use App\Models\IndikatorKinerja;
use App\Models\JadwalSnapshot;
use App\Models\JadwalTahunan;
use App\Models\Kegiatan;
use App\Models\KlaimKegiatan;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiTarget;
use App\Models\User;
use App\Services\Authorization\PermissionResolver;
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KesiapanPengajuan;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Services\RencanaAksi\RekonsiliasiTargetDraf;
use App\Support\DeviasiTargetPk;
use App\Support\PerbandinganNilaiRencanaAksi;
use App\Support\PermissionCodes;
use App\Support\PermissionDecision;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class IndexRencanaAksi
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly RekonsiliasiTargetDraf $rekonsiliasi,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly KonteksBekuRencanaAksi $konteks,
        private readonly KesiapanPengajuan $kesiapan,
        private readonly EvaluateEvidence $evaluator,
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
     * `KesiapanPengajuan` (mesin `CalculatePengukuran`) agar React tidak
     * menghitung skor turunan sendiri.
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

            $baris = $this->kesiapan->petaTarget(RencanaAksiTarget::where('rencana_aksi_id', $segel->id)->get());

            // Baris basi transisi (tak efektif pada
            // satu pun versi antara jepit→terbaru) disaring bagai tak
            // ada — sel tampil kosong. Tanpa efek samping: penghapusan
            // + audit milik jalur tulis berikutnya.
            $baris = $this->rekonsiliasi->saringPetaBasi($baris, $this->rekonsiliasi->rekonsiliasi($segel, $snapshot)['kunci']);

            // Baris periode beku (bentuk yang dibekukan saat pengajuan) adalah
            // satu-satunya sumber skor dan deviasi di halaman ini, sehingga
            // angka yang tampil, kesiapan, dan versi beku tidak dapat berbeda.
            // Dihitung dari peta yang sudah disaring agar sel basi tidak
            // dihitung lengkap. Bukti current dibaca lagi oleh
            // `IndexBuktiRencanaAksi` untuk panel (terbatas).
            $barisBeku = $this->kesiapan->barisPeriode($tipe, $presisi, $definisi, $jendela, $efektifIds, $baris);
            $deviasiBeku = $this->kesiapan->deviasi($barisBeku, $snapshot);
            $persyaratan = $this->evaluator->untuk('rencana_aksi', (string) $indikator->id, $segel->buktiDukungs()->current()->get(['id', 'jenis_berkas_id', 'mode']));
            $alasanPengajuan = $this->kesiapan->alasanTolak($barisBeku, $deviasiBeku, $segel->alasan_deviasi_pk, $persyaratan);
            // Gerbang kegiatan memakai aturan yang sama dengan pengajuan; jalur dinilai dari izin ajukan pembaca.
            $klaim = KlaimKegiatan::where('rencana_aksi_id', $segel->id)->where('sumber_klaim', 'rencana_aksi')->get();
            $alasanPengajuan = [...$alasanPengajuan, ...$this->kesiapan->alasanKlaim(
                $klaim, Kegiatan::whereIn('id', $klaim->pluck('kegiatan_id'))->get()->keyBy('id'), $segel, $efektifIds, $definisi->pluck('komponen_id')->all(),
            )];
            $alasanKegiatan = $this->kesiapan->alasanKegiatan(
                $klaim->count(),
                (int) $segel->tahun,
                $this->jendela->jalurPerencanaan($this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_AJUKAN, (string) $segel->unit_id)),
                $jadwal, $jendela, $efektifIds,
            );
            if ($alasanKegiatan !== null) {
                $alasanPengajuan[] = $alasanKegiatan;
            }
            // Jepit draf tertinggal dari snapshot koreksi terbaru: gerbang
            // pengajuan menolak 409 sampai target disimpan di konteks baru, jadi
            // halaman menunjukkan tindakannya alih-alih "siap" tanpa tombol.
            if ((string) $segel->snapshot_draf_id !== (string) $snapshot->id) {
                array_unshift($alasanPengajuan, 'Konteks indikator berubah (snapshot koreksi baru terbit). Simpan target sebelum mengajukan.');
            }

            $periode = $this->susunPeriode($tipe, $definisi, $jendela, $efektifIds, $baris, collect($barisBeku)->keyBy('periode_id'));
            $koreksi = $this->statusKoreksi($jadwal, $indikator);

            $unitId = (string) $segel->unit_id;
            $keputusanUbah = $this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_UPDATE, $unitId);
            // Daftar periode terkunci bagi aktor untuk UI hanya-baca per
            // periode; Perencanaan dikecualikan sehingga daftarnya kosong.
            // Dalam sesi koreksi aktif daftar juga dikosongkan: lingkup
            // koreksi sudah menjadi pembatas dan kunci tanggal diabaikan
            // (cermin `penilaian()`), sehingga periode dalam lingkup tidak
            // dikunci UI.
            $hariIni = today(config('app.business_timezone'))->toDateString();
            $terkunciIds = ($this->jendela->jalurPerencanaan($keputusanUbah) || $koreksi['aktif'])
                ? []
                : array_column($this->jendela->rincianTerkunci($jadwal, $efektifIds->all(), $hariIni), 'id');
            // Hanya kolom ringkas; jsonb `snapshot` versi tidak dibutuhkan halaman.
            $versiPengajuan = $segel->latestVersion()
                ->leftJoin('users', 'users.id', '=', 'rencana_aksi_versi.diajukan_by')
                ->first(['rencana_aksi_versi.nomor', 'rencana_aksi_versi.diajukan_at', 'users.nama as diajukan_oleh']);

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
                'deviasi_pk' => DeviasiTargetPk::props($deviasiBeku, $segel->alasan_deviasi_pk, $barisBeku === [] ? null : $barisBeku[array_key_last($barisBeku)]['periode_id']),
                // Teks alasan berasal dari server; React hanya menampilkannya.
                'pengajuan' => ['siap' => $alasanPengajuan === [], 'alasan' => $alasanPengajuan],
                'versi_pengajuan' => $versiPengajuan === null ? null : [
                    'nomor' => (int) $versiPengajuan->nomor,
                    'diajukan_oleh' => $versiPengajuan->getAttribute('diajukan_oleh'),
                    'diajukan_at' => $versiPengajuan->diajukan_at->toIso8601String(),
                ],
                // Daftar kunci untuk UI hanya-baca per periode.
                'periode_terkunci' => $terkunciIds,
                // `update` memakai syarat gerbang tulis simpan berbasis
                // perubahan (Q41): tetap true selama masih ada periode yang
                // dapat ditulis (efektif dikurangi terkunci; dalam koreksi
                // hanya irisan efektif ∩ lingkup) agar formulir tidak dibuka
                // untuk penyimpanan yang pasti ditolak.
                'can' => [
                    'view' => $this->resolver->allows($actor, PermissionCodes::RENCANA_AKSI_READ, $unitId),
                    'update' => $this->bolehUbah($actor, $keputusanUbah, $segel, $indikator, $jadwal, $efektifIds, $koreksi, $terkunciIds),
                    'submit' => $this->bolehAjukan($actor, $segel, $snapshot, $indikator, $jadwal, $efektifIds, $barisBeku),
                ],
            ];
        });
    }

    /**
     * Periode yang dapat ditulis: irisan efektif ∩ lingkup dalam sesi
     * koreksi (kunci tanggal diabaikan di sana); di luar itu efektif
     * dikurangi terkunci bagi aktor.
     *
     * @param  Collection<int, string>  $efektifIds
     * @param  array{aktif: bool, periode_ids: list<string>}  $koreksi
     * @param  list<string>  $terkunciIds
     * @return list<string>
     */
    private function periodeDapatDitulis(Collection $efektifIds, array $koreksi, array $terkunciIds): array
    {
        if ($koreksi['aktif']) {
            return array_values(array_intersect($efektifIds->all(), $koreksi['periode_ids']));
        }

        return array_values(array_diff($efektifIds->all(), $terkunciIds));
    }

    private function bolehUbah(User $actor, PermissionDecision $keputusanUbah, RencanaAksi $segel, IndikatorKinerja $indikator, JadwalTahunan $jadwal, Collection $efektifIds, array $koreksi, array $terkunciIds): bool
    {
        if (! $keputusanUbah->allowed
            || $segel->unit?->status !== 'aktif'
            || $indikator->isArsip()
            || ! in_array($segel->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
            return false;
        }

        $dapatDitulis = $this->periodeDapatDitulis($efektifIds, $koreksi, $terkunciIds);
        if ($dapatDitulis === []) {
            return false;
        }

        return $this->jendela->penilaian($actor, $keputusanUbah, $indikator, $jadwal, 'penyimpanan', $dapatDitulis)['pesan'] === null;
    }

    /**
     * Eligibility gerbang pengajuan tanpa kelengkapan isi: status dapat
     * disunting, jepit snapshot terbaru, unit aktif, indikator bukan arsip,
     * izin ajukan, dan kunci berbasis perubahan yang sama dengan
     * `AjukanRencanaAksi` lewat `PerbandinganNilaiRencanaAksi` (periode yang
     * dinilai adalah yang berubah dibanding versi sah terakhir; tanpa
     * pembanding dinilai kosong).
     * Kelengkapan dilaporkan terpisah di `pengajuan` agar UI dapat
     * menunjukkan alasannya. Pemeriksaan murah didahulukan agar resolver
     * tidak dipanggil untuk status beku.
     *
     * @param  Collection<int, string>  $efektifIds
     * @param  list<array{periode_id: string, nilai: mixed, komponen: list<array{komponen_id: string, nilai: mixed}>}>  $barisBeku
     */
    private function bolehAjukan(User $actor, RencanaAksi $segel, JadwalSnapshot $snapshot, IndikatorKinerja $indikator, JadwalTahunan $jadwal, Collection $efektifIds, array $barisBeku): bool
    {
        if (! in_array($segel->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)
            || (string) $segel->snapshot_draf_id !== (string) $snapshot->id
            || $segel->unit?->status !== 'aktif'
            || $indikator->isArsip()) {
            return false;
        }

        $keputusan = $this->resolver->resolve($actor, PermissionCodes::RENCANA_AKSI_AJUKAN, (string) $segel->unit_id);
        if (! $keputusan->allowed) {
            return false;
        }

        $berubah = PerbandinganNilaiRencanaAksi::periodeBerubahVsVersiSah((string) $segel->id, $barisBeku);

        return $this->jendela->penilaian($actor, $keputusan, $indikator, $jadwal, 'pengajuan', $berubah)['pesan'] === null;
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
     * @param  Collection<int, PeriodeJadwal>  $jendela
     * @param  Collection<int, string>  $efektifIds
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @param  Collection<string, Collection<string, RencanaAksiTarget>>  $baris
     * @param  Collection<string, array{nilai: ?string, status_perhitungan: string}>  $barisBeku  Skor per `periode_id` dari `KesiapanPengajuan::barisPeriode`.
     * @return list<array<string, mixed>>
     */
    private function susunPeriode(string $tipe, Collection $definisi, Collection $jendela, Collection $efektifIds, Collection $baris, Collection $barisBeku): array
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
            $beku = $barisBeku->get($periodeId);
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
                'skor' => ['nilai' => $beku['nilai'] ?? null, 'status_perhitungan' => $beku['status_perhitungan'] ?? 'belum_diisi'],
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
}
