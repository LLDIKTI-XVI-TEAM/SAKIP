<?php

namespace App\Services\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Services\Jadwal\SesiKoreksiJadwal;
use App\Support\PermissionDecision;
use Carbon\CarbonInterface;

/**
 * Aturan tulis Rencana Aksi Q41: penutupan tahun (status `ditutup` atau
 * tanggal `penutupan`) beserta sesi koreksi,
 * jalur Perencanaan global, PIC efektif, dan kunci per periode mengikuti
 * `jadwal_periode.pengisian_selesai` yang berlaku pada tanggal Asia/Makassar.
 *
 * Sejak Q41 jendela penyusunan dihapus; pembatas tulis PIC kini kunci per
 * periode. PIC efektif dengan grant yang sah dapat menulis sejak jadwal
 * `aktif` sampai `penutupan`; periode P terkunci bagi PIC setelah
 * `pengisian_selesai` P terlewati — termasuk periode lampau saat aktivasi
 * (tanggalnya sudah lewat sehingga otomatis terkunci) dan hasil revisi resmi
 * jendela (Q18) karena yang dibaca selalu nilai berlaku. Sesi koreksi
 * hanya membuka jalur Perencanaan pada waktu dan lingkup sah; pembukaan
 * PIC memerlukan aksi resmi terpisah, bukan perubahan tanggal saja.
 *
 * Dipisah agar gerbang tulis (`EnsureDraftRencanaAksi`,
 * `SimpanTargetPeriode`, `AjukanRencanaAksi`, `GerbangBuktiRencanaAksi`,
 * `GerbangKlaimKegiatan`, `SimpanKegiatan`, `TambahKegiatanRencanaAksi`,
 * `HapusKlaimKegiatan`), gerbang review (`GerbangReviewRencanaAksi`), serta
 * capability baca (`IndexRencanaAksi`, `DaftarRencanaAksi`,
 * `DaftarKlaimKegiatan`) memakai satu sumber aturan, sehingga UI tidak
 * menawarkan aksi yang pasti ditolak.
 * Service ini hanya menilai; izin dasar (`PermissionDecision`) diputuskan
 * pemanggil lewat resolver, sedangkan transaksi, kunci baris, pelemparan
 * error, dan audit tetap milik Action.
 * Pemanggil tulis wajib meneruskan indikator dan jadwal yang sudah dikunci
 * agar keputusan memakai state terkini. PIC efektif dibaca tanpa kunci
 * karena writer PJ juga mengunci indikator, yang sudah dikunci pemanggil
 * tulis. `DaftarRencanaAksi` meneruskan PIC efektif yang sudah di-join dari
 * scope `PenugasanIndikator::effectiveOn` yang sama agar tidak membaca ulang
 * penugasan per baris; `AjukanRencanaAksi` meneruskan PJ yang dibacanya
 * setelah mengunci indikator agar gerbang dan provenance memakai PJ yang sama.
 */
class JendelaTulisRencanaAksi
{
    /**
     * Alasan penolakan tulis dalam Bahasa Indonesia, atau null bila boleh.
     *
     * @param  'pembuatan'|'penyimpanan'|'pengajuan'  $tindakan
     * @param  list<string>  $periodeIds  Periode yang hendak ditulis; kosong untuk pembuatan
     *                                    header atau capability tanpa dimensi periode.
     *                                    Pengajuan menilai periode yang berubah dari target
     *                                    dan klaim tahap RA terhadap versi sah. Writer klaim
     *                                    juga menilai periode kegiatan saat mutasi klaim.
     * @param  string|null  $picUserId  PIC efektif hari ini yang sudah dibaca pemanggil;
     *                                  null berarti dibaca di sini. Capability baca
     *                                  (`DaftarRencanaAksi`) mengisinya dari join. Gerbang
     *                                  tulis hanya boleh mengisinya dengan PJ yang dibacanya
     *                                  sendiri setelah indikator dikunci (`AjukanRencanaAksi`,
     *                                  agar provenance memakai PJ yang sama), tidak pernah
     *                                  dari input klien.
     * @param  CarbonInterface|null  $pada  Waktu keputusan; null berarti sekarang. Pengajuan
     *                                      meneruskan waktu yang juga dibekukan sebagai
     *                                      `diajukan_at` agar jendela, penutupan, sesi koreksi,
     *                                      dan PJ dinilai pada satu waktu.
     */
    public function alasanTolak(User $aktor, PermissionDecision $keputusan, IndikatorKinerja $indikator, JadwalTahunan $jadwal, string $tindakan, array $periodeIds = [], ?string $picUserId = null, ?CarbonInterface $pada = null): ?string
    {
        return $this->penilaian($aktor, $keputusan, $indikator, $jadwal, $tindakan, $periodeIds, $picUserId, $pada)['pesan'];
    }

    /**
     * Penilaian terstruktur gerbang tulis agar pemanggil tidak mencocokkan
     * teks pesan untuk membedakan jenis penolakan.
     *
     * @param  'pembuatan'|'penyimpanan'|'pengajuan'  $tindakan
     * @param  list<string>  $periodeIds
     * @return array{jenis: 'lolos'|'penutupan'|'pic'|'kunci', pesan: string|null, terkunci: list<array{id: string, nama: string, batas: string, urutan: int}>}
     */
    public function penilaian(User $aktor, PermissionDecision $keputusan, IndikatorKinerja $indikator, JadwalTahunan $jadwal, string $tindakan, array $periodeIds = [], ?string $picUserId = null, ?CarbonInterface $pada = null): array
    {
        $pada ??= now();
        if ($this->tahunDitutup($jadwal, $pada) && (! $this->jalurPerencanaan($keputusan) || ! $this->dalamKoreksiSah($indikator, $jadwal, $periodeIds, $pada))) {
            return ['jenis' => 'penutupan', 'pesan' => "Tahun jadwal telah ditutup; {$tindakan} memerlukan sesi koreksi resmi.", 'terkunci' => []];
        }

        if ($this->jalurPerencanaan($keputusan)) {
            return ['jenis' => 'lolos', 'pesan' => null, 'terkunci' => []];
        }

        $hariIni = $this->tanggalBisnis($pada);
        $picUserId ??= PenugasanIndikator::effectiveOn($hariIni)->where('indikator_id', $indikator->id)->value('user_id');
        if ($picUserId === null || (string) $picUserId !== (string) $aktor->id) {
            return ['jenis' => 'pic', 'pesan' => 'Tindakan ini memerlukan penugasan PIC yang efektif.', 'terkunci' => []];
        }

        $terkunci = $this->rincianTerkunci($jadwal, $periodeIds, $hariIni);
        if ($terkunci !== []) {
            $pesan = 'Periode terkunci bagi PIC setelah batas pengisian lewat: '.implode('; ', array_map(fn (array $baris): string => "{$baris['nama']} (batas {$baris['batas']})", $terkunci)).'.';

            return ['jenis' => 'kunci', 'pesan' => $pesan, 'terkunci' => $terkunci];
        }

        return ['jenis' => 'lolos', 'pesan' => null, 'terkunci' => []];
    }

    /**
     * Tahun terkunci menurut aturan bersama jadwal: status `ditutup` atau
     * tanggal `penutupan` terlewati, atau metadata sesi koreksi sudah ada.
     */
    public function tahunDitutup(JadwalTahunan $jadwal, ?CarbonInterface $pada = null): bool
    {
        return $jadwal->tahunDitutup($pada);
    }

    /**
     * Sesi koreksi sah bila jendela waktunya berjalan pada `$pada` (default
     * sekarang), `jenis_objek` memuat `rencana_aksi`, dan indikator tercakup.
     */
    public function sesiKoreksiAktif(IndikatorKinerja $indikator, JadwalTahunan $jadwal, ?CarbonInterface $pada = null): bool
    {
        return SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', (string) $indikator->id, pada: $pada);
    }

    /**
     * Periode dalam `lingkup_koreksi.periode_ids`. Kunci yang tidak ada
     * berarti tidak ada periode tercakup (gagal tertutup, sama dengan
     * PengukuranKinerjaPolicy).
     *
     * @return list<string>
     */
    public function periodeLingkupKoreksi(JadwalTahunan $jadwal): array
    {
        $periodeIds = $jadwal->lingkup_koreksi['periode_ids'] ?? [];

        return is_array($periodeIds) ? array_values(array_map(fn ($id): string => (string) $id, $periodeIds)) : [];
    }

    /**
     * Jalur global bila allow berasal dari peran, bukan hanya grant unit
     * (Data Model §2.23; ADR-0002). Allow yang hanya dari grant adalah jalur
     * PIC ber-scope unit yang tunduk pada PJ efektif dan jendela. Publik agar
     * pengajuan membekukan `jalur_pengajuan` dari aturan yang sama.
     */
    public function jalurPerencanaan(PermissionDecision $keputusan): bool
    {
        return $keputusan->allowed && ($keputusan->basis['sumber_allow']['roles'] ?? []) !== [];
    }

    /**
     * Rincian periode terkunci bagi PIC (untuk gerbang pengajuan berbasis
     * perubahan nilai Q41 §41.2): setiap periode yang `pengisian_selesai`
     * berlakunya sudah lewat pada tanggal bisnis, terurut urutan periode
     * lalu nama agar pesan 422 spesifik per periode dan stabil.
     *
     * Periode yang tidak dikenal atau bukan anggota jadwal diabaikan di
     * sini; validasi keanggotaan milik pemanggil (`SimpanTargetPeriode`).
     *
     * @param  list<string>  $periodeIds
     * @return list<array{id: string, nama: string, batas: string, urutan: int}>
     */
    public function rincianTerkunci(JadwalTahunan $jadwal, array $periodeIds, string $hariIni): array
    {
        $ids = array_values(array_unique(array_map(fn ($id): string => (string) $id, $periodeIds)));
        if ($ids === []) {
            return [];
        }

        $batas = PeriodeJadwal::where('jadwal_id', $jadwal->id)->whereIn('periode_id', $ids)->pluck('pengisian_selesai', 'periode_id');
        $nama = Periode::whereIn('id', $ids)->pluck('nama', 'id');
        $urutan = Periode::whereIn('id', $ids)->pluck('urutan', 'id');

        $terkunci = [];
        foreach ($ids as $id) {
            $selesai = $batas->get($id);
            $tanggal = $selesai instanceof CarbonInterface ? $selesai->toDateString() : (is_string($selesai) ? substr($selesai, 0, 10) : null);
            if ($tanggal === null || $tanggal === '' || $tanggal >= $hariIni) {
                continue;
            }
            $terkunci[] = ['id' => $id, 'nama' => (string) ($nama->get($id) ?? $id), 'batas' => $tanggal, 'urutan' => (int) ($urutan->get($id) ?? 0)];
        }
        usort($terkunci, fn (array $a, array $b): int => [$a['urutan'], $a['nama']] <=> [$b['urutan'], $b['nama']]);

        return $terkunci;
    }

    /**
     * Periode yang hendak ditulis tetapi `pengisian_selesai` berlakunya
     * sudah lewat pada tanggal bisnis, terurut urutan periode lalu nama
     * agar pesan 422 spesifik per periode dan stabil.
     *
     * Periode yang tidak dikenal atau bukan anggota jadwal diabaikan di
     * sini; validasi keanggotaan milik pemanggil (`SimpanTargetPeriode`).
     *
     * @param  list<string>  $periodeIds
     * @return list<string>
     */
    private function periodeTerkunci(JadwalTahunan $jadwal, array $periodeIds, string $hariIni): array
    {
        return array_map(fn (array $baris): string => "{$baris['nama']} (batas {$baris['batas']})", $this->rincianTerkunci($jadwal, $periodeIds, $hariIni));
    }

    /**
     * Setiap periode yang hendak ditulis wajib tercantum di lingkup koreksi;
     * acuannya periode yang diminta, bukan yang tersimpan, sehingga header
     * tanpa target lama pun tetap divalidasi.
     *
     * @param  list<string>  $periodeIds
     */
    private function dalamKoreksiSah(IndikatorKinerja $indikator, JadwalTahunan $jadwal, array $periodeIds, CarbonInterface $pada): bool
    {
        return SesiKoreksiJadwal::berlaku($jadwal, 'rencana_aksi', (string) $indikator->id, $periodeIds, $pada);
    }

    private function tanggalBisnis(CarbonInterface $pada): string
    {
        return $pada->copy()->setTimezone(config('app.business_timezone'))->toDateString();
    }
}
