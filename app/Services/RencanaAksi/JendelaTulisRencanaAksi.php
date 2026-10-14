<?php

namespace App\Services\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\Periode;
use App\Models\PeriodeJadwal;
use App\Models\User;
use App\Support\PermissionDecision;
use Carbon\CarbonInterface;

/**
 * Aturan tulis Rencana Aksi Q41: penutupan tahun beserta sesi koreksi,
 * jalur Perencanaan global, PIC efektif, dan kunci per periode mengikuti
 * `jadwal_periode.pengisian_selesai` yang berlaku pada tanggal Asia/Makassar.
 *
 * Sejak Q41 tidak ada jendela penyusunan: kolom
 * `jadwal_tahunan.rencana_aksi_mulai`/`rencana_aksi_selesai` dibiarkan di
 * skema tetapi tidak dibaca di sini. PIC efektif dengan grant yang sah
 * dapat menulis sejak jadwal `aktif` sampai `penutupan`; periode P
 * terkunci bagi PIC setelah `pengisian_selesai` P terlewati — termasuk
 * periode lampau saat aktivasi (tanggalnya sudah lewat sehingga otomatis
 * terkunci) dan hasil revisi resmi jendela (Q18) karena yang dibaca selalu
 * nilai berlaku. Dalam sesi koreksi sah pascapenutupan, kunci tanggal
 * tidak diterapkan; sebagai gantinya hanya periode dalam lingkup koreksi
 * yang boleh ditulis (Q41 §41.2 butir 6).
 *
 * Dipisah agar gerbang tulis (`EnsureDraftRencanaAksi`,
 * `SimpanTargetPeriode`, `AjukanRencanaAksi`) dan capability baca
 * (`IndexRencanaAksi`, `DaftarRencanaAksi`) memakai satu sumber aturan, sehingga UI tidak
 * menawarkan aksi yang pasti ditolak. Service ini hanya menilai; izin dasar
 * (`PermissionDecision`) diputuskan pemanggil lewat resolver, sedangkan
 * transaksi, kunci baris, pelemparan error, dan audit tetap milik Action.
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
     *                                    Pengajuan memakai seluruh periode efektif karena
     *                                    menyentuh seluruh RA; daftar kegiatan tahap RA
     *                                    (`sumber_klaim = rencana_aksi`) belum ada
     *                                    writer-nya sehingga guard pada tahap ini baru
     *                                    menilai target periode.
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
        $pada ??= now();
        if ($this->tahunDitutup($jadwal, $pada) && ! $this->dalamKoreksiSah($indikator, $jadwal, $periodeIds, $pada)) {
            return "Tahun jadwal telah ditutup; {$tindakan} memerlukan sesi koreksi resmi.";
        }

        if ($this->jalurPerencanaan($keputusan)) {
            return null;
        }

        $hariIni = $this->tanggalBisnis($pada);
        $picUserId ??= PenugasanIndikator::effectiveOn($hariIni)->where('indikator_id', $indikator->id)->value('user_id');
        if ($picUserId === null || (string) $picUserId !== (string) $aktor->id) {
            return 'Tindakan ini memerlukan penugasan PIC yang efektif.';
        }

        // Dalam sesi koreksi yang sah, lingkup periode sudah divalidasi pada
        // gerbang penutupan di atas; kunci tanggal tidak diterapkan agar
        // periode yang jendela pengisiannya dibuka resmi dapat dikoreksi.
        if ($this->tahunDitutup($jadwal, $pada)) {
            return null;
        }

        $terkunci = $this->periodeTerkunci($jadwal, $periodeIds, $hariIni);
        if ($terkunci !== []) {
            return 'Periode terkunci bagi PIC setelah batas pengisian lewat: '.implode('; ', $terkunci).'.';
        }

        return null;
    }

    /**
     * Tanggal penutupan jadwal sudah terlewati pada tanggal bisnis `$pada`
     * (default sekarang).
     */
    public function tahunDitutup(JadwalTahunan $jadwal, ?CarbonInterface $pada = null): bool
    {
        $penutupan = $jadwal->penutupan?->toDateString();

        return is_string($penutupan) && $this->tanggalBisnis($pada ?? now()) > $penutupan;
    }

    /**
     * Sesi koreksi sah bila jendela waktunya berjalan pada `$pada` (default
     * sekarang), `jenis_objek` memuat `rencana_aksi`, dan indikator tercakup.
     */
    public function sesiKoreksiAktif(IndikatorKinerja $indikator, JadwalTahunan $jadwal, ?CarbonInterface $pada = null): bool
    {
        if (! $jadwal->koreksi_mulai instanceof CarbonInterface || ! $jadwal->koreksi_sampai instanceof CarbonInterface) {
            return false;
        }
        if (! ($pada ?? now())->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)) {
            return false;
        }
        $lingkup = $jadwal->lingkup_koreksi ?? [];

        return in_array('rencana_aksi', $lingkup['jenis_objek'] ?? [], true)
            && in_array($indikator->id, $lingkup['indikator_ids'] ?? [], true);
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
            $terkunci[] = ['nama' => (string) ($nama->get($id) ?? $id), 'batas' => $tanggal, 'urutan' => (int) ($urutan->get($id) ?? 0)];
        }
        usort($terkunci, fn (array $a, array $b): int => [$a['urutan'], $a['nama']] <=> [$b['urutan'], $b['nama']]);

        return array_map(fn (array $baris): string => "{$baris['nama']} (batas {$baris['batas']})", $terkunci);
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
        return $this->sesiKoreksiAktif($indikator, $jadwal, $pada)
            && array_diff($periodeIds, $this->periodeLingkupKoreksi($jadwal)) === [];
    }

    private function tanggalBisnis(CarbonInterface $pada): string
    {
        return $pada->copy()->setTimezone(config('app.business_timezone'))->toDateString();
    }
}
