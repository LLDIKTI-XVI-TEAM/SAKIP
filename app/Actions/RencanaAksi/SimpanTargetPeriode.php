<?php

namespace App\Actions\RencanaAksi;

use App\Actions\Pengukuran\CalculatePengukuran;
use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
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
use App\Services\RencanaAksi\JendelaTulisRencanaAksi;
use App\Services\RencanaAksi\KonteksBekuRencanaAksi;
use App\Services\RencanaAksi\RekonsiliasiTargetDraf;
use App\Support\AlasanAudit;
use App\Support\PermissionCodes;
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
        private readonly RekonsiliasiTargetDraf $rekonsiliasi,
        private readonly JendelaTulisRencanaAksi $jendela,
        private readonly KonteksBekuRencanaAksi $konteks,
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
     * (token `expected_snapshot_id`/`expected_snapshot_versi` ikut
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

                $keputusan = $this->resolver->resolve($pengunci, PermissionCodes::RENCANA_AKSI_UPDATE, (string) $header->unit_id);
                $dasarIzin = $keputusan->toAuditBasis();
                if (! $keputusan->allowed) {
                    throw new AuthorizationException('Izin penyimpanan target rencana aksi tidak tersedia atau telah dicabut.');
                }

                $this->arsipGuard->pastikanDapatDibuatkan($indikator, 'rencana_aksi');

                if (! in_array($header->status_alur, RencanaAksi::STATUS_DAPAT_DISUNTING, true)) {
                    throw ValidationException::withMessages(['status_alur' => 'Target hanya dapat disimpan pada draf yang dapat disunting.']);
                }

                $expected = (int) ($data['expected_versi'] ?? 0);
                if ($header->versi !== $expected) {
                    throw ValidationException::withMessages(['expected_versi' => 'Data telah berubah. Muat ulang sebelum mengulangi penyimpanan.'])->status(409);
                }

                // Target selalu ditulis di bawah snapshot beku; header yang
                // jadwalnya belum pernah aktif (tanpa snapshot) ditolak
                // fail-closed, bukan dihitung dari master berjalan.
                $snapshot = $this->konteks->snapshotTerbaru($jadwal->id, $indikator->id, kunci: true)
                    ?? throw ValidationException::withMessages(['snapshot' => 'Konteks indikator beku untuk jadwal ini tidak tersedia; penyimpanan ditolak.']);

                // Guard keselarasan unit jalur update (lanjutan guard unit yang
                // hanya di create). Auth dievaluasi terhadap header.unit_id,
                // sementara konteks efektif berasal dari snapshot terbaru —
                // bila keduanya berbeda, tolak fail-closed agar RA tidak lolos
                // tulis di sini lalu ditolak SubmissionPrerequisites.
                if ((string) $snapshot->unit_id !== (string) $header->unit_id) {
                    throw ValidationException::withMessages(['snapshot' => 'Unit pemilik rencana aksi tidak selaras dengan konteks beku terbaru; penyimpanan ditolak sampai snapshot koreksi tersedia.']);
                }

                // Token konkurensi snapshot (eksplisit,
                // tanpa bump semu versi header) WAJIB pada setiap penyimpanan.
                // Snapshot koreksi baru yang terbit antara baca-simpan mengubah
                // konteks diam-diam (tipe/bobot/presisi/periode-mulai)
                // sementara ID komponen sama — simpan dengan token lama/usang
                // (termasuk kunci hilang yang dinormalisasi menjadi null, atau
                // null eksplisit) ditolak 409 agar nilai tak diterima dengan
                // konteks yang tak pernah dilihat pengguna. Tanpa jalur bypass:
                // perbandingan selalu dijalankan, bukan hanya bila kunci ada.
                $tokenId = $data['expected_snapshot_id'] ?? null;
                $tokenVersi = $data['expected_snapshot_versi'] ?? null;
                $tokenId = $tokenId === null ? null : (string) $tokenId;
                $tokenVersi = $tokenVersi === null ? null : (int) $tokenVersi;
                $aktualId = (string) $snapshot->id;
                $aktualVersi = (int) $snapshot->nomor_versi;
                if ($tokenId !== $aktualId || $tokenVersi !== $aktualVersi) {
                    throw ValidationException::withMessages(['expected_snapshot_id' => 'Konteks indikator berubah (snapshot koreksi baru terbit). Muat ulang sebelum mengulangi penyimpanan.'])->status(409);
                }

                $tipe = (string) $snapshot->tipe_perhitungan;
                $presisi = (int) $snapshot->presisi;
                $definisi = $this->konteks->komponen($snapshot, kunci: true);
                $periodeEfektif = $this->konteks->periodeEfektif($snapshot, $this->konteks->jendela($jadwal->id, kunci: true));
                $anggotaJadwal = PeriodeJadwal::where('jadwal_id', $jadwal->id)->pluck('periode_id')->map(fn ($id): string => (string) $id)->all();

                $targets = $this->normalisasiTargets($data['targets'] ?? []);
                $alasan = $this->jendela->alasanTolak($pengunci, $keputusan, $indikator, $jadwal, 'penyimpanan', array_column($targets, 'periode_id'));
                if ($alasan !== null) {
                    throw ValidationException::withMessages(['jendela' => $alasan]);
                }
                $this->pastikanTargetsSah($tipe, $presisi, $definisi, $periodeEfektif, $anggotaJadwal, $targets);

                // Rekonsiliasi transisi:
                // baris yang tak efektif pada satu pun versi antara
                // jepit→terbaru (mis. v1 → v2 tanpa simpan → v3) ditandai
                // basi. Dihapus setelah upsert berdasarkan kunci dimensi,
                // KECUALI dimensi yang eksplisit dikirim bernilai dalam
                // konteks terbaru (keputusan kecualikan-kiriman, bukan
                // purge-sebelum-upsert — agar nilai baru 200 untuk dimensi
                // yang pulih di v3 tidak ikut terhapus, sementara kiriman
                // kosong (null+null) tetap dibersihkan bagai tak ada dan
                // koreksi parsial yang tak terkirim tak ikut terpurge karena
                // tak ada di himpunan basi). Kiriman basi yang sengaja tak
                // efektif-kini tetap milik `bersihkanDimensiTakEfektif`.
                $jejak = $this->rekonsiliasi->rekonsiliasi($header, $snapshot);

                $sebelum = $this->auditState($header);
                $header->fill([
                    'uraian' => array_key_exists('uraian', $data) ? $this->normalisasiTeks($data['uraian']) : $header->uraian,
                    'alasan_deviasi_pk' => array_key_exists('alasan_deviasi_pk', $data) ? $this->normalisasiTeks($data['alasan_deviasi_pk']) : $header->alasan_deviasi_pk,
                    // Majukan jepit ke snapshot terbaru yang dipakai simpan ini.
                    'snapshot_draf_id' => $aktualId,
                ]);
                $header->versi++;
                $header->save();

                // Header FOR UPDATE menyerialkan simpan, jadi sel yang belum ada
                // di sini aman dibuat baru (index unik tetap menjaga duplikasi).
                $tersimpan = RencanaAksiTarget::where('rencana_aksi_id', $header->id)->lockForUpdate()->get()
                    ->keyBy(fn (RencanaAksiTarget $row): string => $this->rekonsiliasi->kunciDimensi($row->periode_id, $row->komponen_id));
                foreach ($targets as $baris) {
                    ($tersimpan->get($this->rekonsiliasi->kunciDimensi($baris['periode_id'], $baris['komponen_id']))
                        ?? new RencanaAksiTarget(['rencana_aksi_id' => $header->id, 'periode_id' => $baris['periode_id'], 'komponen_id' => $baris['komponen_id']]))
                        ->fill(['nilai' => $baris['nilai'], 'keterangan' => $baris['keterangan'], 'updated_by' => $pengunci->id, 'updated_at' => now()])
                        ->save();
                }

                // Hanya sel yang efektif KEMBALI di bawah konteks terbaru
                // yang dibuang di sini (kandidat bangkit); sel yang tak
                // efektif di bawah konteks terbaru tetap milik
                // `bersihkanDimensiTakEfektif` (kontrak pembersihan dimensi tetap utuh).
                // Kecualikan dimensi terkirim bernilai:
                // jangan hapus input baru untuk dimensi yang pulih di v3.
                $kunciPurge = array_values(array_diff(
                    $this->kunciBasiEfektifKini($jejak['kunci'], $tipe, $definisi, $periodeEfektif),
                    $this->kunciKirimBernilaiEfektif($targets, $tipe, $definisi, $periodeEfektif)
                ));
                $barisBasi = $this->buangKunciBasi($header->id, $kunciPurge);

                // Singkirkan baris draf yang tak
                // lagi efektif di bawah konteks snapshot terbaru (komponen
                // dihapus, tipe manual↔nonmanual, periode pra-berlaku).
                // Upsert hanya menyentuh sel terkirim sehingga baris lama
                // melekat tanpa identitas — tersembunyi dari baca namun bisa
                // muncul kembali bila konteks berbalik. Dihapus eksplisit di
                // transaksi yang sama; selisihnya terekam di audit
                // nilai_lama/nilai_baru + jumlah pada alasan.
                $barisDisingkirkan = $this->bersihkanDimensiTakEfektif($header->id, $tipe, $definisi, $periodeEfektif);

                $sesudah = $this->auditState($header->fresh());
                // Batas total pada matriks tersimpan hasil gabungan simpan
                // parsial, bukan payload; lewat batas berarti rollback.
                if (collect($sesudah['targets'])->sum(fn (array $baris): int => mb_strlen((string) $baris['keterangan'])) > 10000) {
                    throw ValidationException::withMessages(['targets' => 'Total keterangan seluruh target melebihi 10.000 karakter.']);
                }
                $alasanSimpan = 'Menyimpan target rencana aksi per periode.';
                if ($barisBasi > 0) {
                    $alasanSimpan .= " Rekonsiliasi transisi snapshot v{$jejak['pin_nomor']}->v{$jejak['aktual_nomor']}: {$barisBasi} baris basi dibersihkan.";
                }
                if ($barisDisingkirkan > 0) {
                    $alasanSimpan .= " Membersihkan {$barisDisingkirkan} baris dimensi tak efektif (konteks snapshot terbaru).";
                }
                $this->audit->catat(
                    actor: $pengunci,
                    tindakan: 'rencana_aksi.ubah',
                    objekTipe: 'rencana_aksi',
                    objekId: (string) $header->id,
                    nilaiLama: $sebelum,
                    nilaiBaru: $sesudah,
                    alasan: $alasanSimpan,
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

    private function pastikanTargetsSah(string $tipe, int $presisi, Collection $definisi, Collection $periodeEfektif, array $anggotaJadwal, array $targets): void
    {
        if ($targets === []) {
            throw ValidationException::withMessages(['targets' => 'Daftar target wajib diisi.']);
        }

        // Validasi periode set-based: satu query untuk
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

    /**
     * Membuang baris basi transisi berdasarkan kunci dimensi
     * (`periode_id::komponen_id`, `manual` untuk baris manual).
     *
     * Dijalankan SETELAH upsert agar kiriman basi yang dikosongkan
     * (null+null dari baca basi maupun klien nakal) ikut terbuang, bukan
     * malah menghidupkan kembali nilai lama. Dimensi yang
     * eksplisit dikirim bernilai (nilai/keterangan non-null) dalam konteks
     * terbaru sudah dikeluarkan dari `$kunci` oleh pemanggil sehingga input
     * baru untuk dimensi yang pulih di v3 tidak ikut terhapus. Selisihnya
     * terekam di audit nilai_lama/nilai_baru.
     *
     * @param  list<string>  $kunci
     */
    private function buangKunciBasi(string $rencanaAksiId, array $kunci): int
    {
        if ($kunci === []) {
            return 0;
        }

        return RencanaAksiTarget::where('rencana_aksi_id', $rencanaAksiId)
            ->where(function ($sub) use ($kunci): void {
                foreach ($kunci as $item) {
                    $potong = explode('::', $item, 2);
                    $periodeId = (string) ($potong[0] ?? '');
                    $komponen = $potong[1] ?? 'manual';
                    $sub->orWhere(function ($sel) use ($periodeId, $komponen): void {
                        $sel->where('periode_id', $periodeId);
                        if ($komponen === 'manual') {
                            $sel->whereNull('komponen_id');
                        } else {
                            $sel->where('komponen_id', $komponen);
                        }
                    });
                }
            })
            ->delete();
    }

    /**
     * Menyaring kunci basi ke sel yang efektif kembali di bawah konteks
     * terbaru — tepat himpunan yang bisa bangkit tanpa jejak ini.
     *
     * @param  list<string>  $kunci
     * @param  Collection<int, string>  $periodeEfektif
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @return list<string>
     */
    private function kunciBasiEfektifKini(array $kunci, string $tipe, Collection $definisi, Collection $periodeEfektif): array
    {
        if ($kunci === []) {
            return [];
        }

        $hitam = array_flip($kunci);
        $komponenEfektif = $tipe === 'manual'
            ? [null]
            : $definisi->pluck('komponen_id')->map(fn ($id): ?string => $id === null ? null : (string) $id)->all();

        $keluar = [];
        foreach ($periodeEfektif as $periodeId) {
            $pid = (string) $periodeId;
            foreach ($komponenEfektif as $komponenId) {
                $calon = $this->rekonsiliasi->kunciDimensi($pid, $komponenId);
                if (isset($hitam[$calon])) {
                    $keluar[] = $calon;
                }
            }
        }

        return $keluar;
    }

    /**
     * Kunci dimensi terkirim bernilai yang efektif di bawah konteks terbaru.
     *
     * Bernilai = `nilai` non-null ATAU `keterangan` non-null (baris kosong
     * ganda-null tetap boleh dibersihkan bagai tak ada). Efektif
     * = periode anggota himpunan efektif + komponen cocok tipe/definisi
     * terbaru, sehingga kiriman basi yang sengaja tak efektif-kini tidak
     * dikecualikan (tetap milik `bersihkanDimensiTakEfektif`) dan koreksi
     * parsial yang tak terkirim tidak ikut terkecuali (tak ada di sini,
     * dipertahankan karena tak ada di himpunan basi).
     *
     * @param  list<array{periode_id: string, komponen_id: string|null, nilai: string|int|float|null, keterangan: string|null}>  $targets
     * @param  Collection<int, string>  $periodeEfektif
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     * @return list<string>
     */
    private function kunciKirimBernilaiEfektif(array $targets, string $tipe, Collection $definisi, Collection $periodeEfektif): array
    {
        if ($targets === []) {
            return [];
        }

        $efektifPeriode = array_flip($periodeEfektif->map(fn ($id): string => (string) $id)->all());
        $efektifKomponen = $tipe === 'manual'
            ? []
            : array_flip($definisi->pluck('komponen_id')->map(fn ($id): string => (string) $id)->all());

        $keluar = [];
        foreach ($targets as $baris) {
            $periodeId = (string) ($baris['periode_id'] ?? '');
            if (! isset($efektifPeriode[$periodeId])) {
                continue;
            }

            $komponenId = $baris['komponen_id'] ?? null;
            $komponenId = $komponenId === null ? null : (string) $komponenId;
            if ($tipe === 'manual') {
                if ($komponenId !== null) {
                    continue;
                }
            } elseif ($komponenId === null || ! isset($efektifKomponen[$komponenId])) {
                continue;
            }

            if (($baris['nilai'] ?? null) === null && ($baris['keterangan'] ?? null) === null) {
                continue;
            }

            $keluar[] = $this->rekonsiliasi->kunciDimensi($periodeId, $komponenId);
        }

        return array_values(array_unique($keluar));
    }

    /**
     * Menyingkirkan baris draf tak efektif di bawah konteks terbaru.
     *
     * Predikat penghapusan = (periode di luar himpunan efektif) ATAU
     * (tipe manual: masih berkomponen) ATAU (tipe nonmanual: baris manual
     * atau komponen di luar himpunan efektif). Periode efektif yang tak
     * terkirim (koreksi parsial 1-dari-N) bukan tak-efektif sehingga
     * dipertahankan — hanya dimensi tak berlaku yang dihapus.
     *
     * Penghapusan aman karena histori per-snapshot dijamin `rencana_aksi_versi`
     * yang beku, bukan tabel draf ini; jumlah dan selisihnya teraudit lewat
     * `rencana_aksi.ubah`. Alternatif mengikat tiap baris ke snapshot asal
     * ditolak: setiap pembaca wajib memfilter pasangan cocok dan baris basi
     * menumpuk selamanya.
     *
     * @param  Collection<int, string>  $periodeEfektif
     * @param  Collection<int, array{komponen_id: string, kode: string, label: string, peran: string, bobot: string, urutan: int}>  $definisi
     */
    private function bersihkanDimensiTakEfektif(string $rencanaAksiId, string $tipe, Collection $definisi, Collection $periodeEfektif): int
    {
        $efektifPeriode = $periodeEfektif->map(fn ($id): string => (string) $id)->values()->all();
        $efektifKomponen = $definisi->pluck('komponen_id')->map(fn ($id): string => (string) $id)->values()->all();

        return RencanaAksiTarget::where('rencana_aksi_id', $rencanaAksiId)
            ->where(function ($sub) use ($tipe, $efektifPeriode, $efektifKomponen): void {
                $sub->whereNotIn('periode_id', $efektifPeriode);
                if ($tipe === 'manual') {
                    $sub->orWhereNotNull('komponen_id');
                } else {
                    $sub->orWhereNull('komponen_id');
                    if ($efektifKomponen === []) {
                        $sub->orWhereNotNull('komponen_id');
                    } else {
                        $sub->orWhereNotIn('komponen_id', $efektifKomponen);
                    }
                }
            })
            ->delete();
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

        return [...$header->only(['status_alur', 'versi', 'snapshot_draf_id', 'uraian', 'alasan_deviasi_pk']), 'targets' => $targets];
    }
}
