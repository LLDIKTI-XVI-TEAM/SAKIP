<?php

namespace App\Services\RencanaAksi;

use App\Models\IndikatorKinerja;
use App\Models\JadwalTahunan;
use App\Models\PenugasanIndikator;
use App\Models\User;
use App\Support\PermissionDecision;
use Carbon\CarbonInterface;

/**
 * Aturan jendela tulis Rencana Aksi: penutupan tahun beserta sesi koreksi,
 * jalur Perencanaan global, PIC efektif, dan jendela `rencana_aksi_mulai`
 * sampai `rencana_aksi_selesai` pada tanggal Asia/Makassar.
 *
 * Dipisah agar gerbang tulis (`EnsureDraftRencanaAksi`,
 * `SimpanTargetPeriode`) dan capability baca (`IndexRencanaAksi`) memakai
 * satu sumber aturan, sehingga UI tidak menawarkan aksi yang pasti ditolak.
 * Service ini hanya menilai; izin dasar (`PermissionDecision`) diputuskan
 * pemanggil lewat resolver, sedangkan transaksi, kunci baris, pelemparan
 * error, dan audit tetap milik Action. Pemanggil tulis wajib meneruskan
 * indikator dan jadwal yang sudah dikunci agar keputusan memakai state
 * terkini. PIC efektif dibaca tanpa kunci karena writer PJ juga mengunci
 * indikator, yang sudah dikunci pemanggil tulis.
 */
class JendelaTulisRencanaAksi
{
    /**
     * Alasan penolakan tulis dalam Bahasa Indonesia, atau null bila boleh.
     *
     * @param  list<string>|null  $periodeIds  Periode yang hendak ditulis; null untuk pembuatan
     *                                         header atau capability yang tidak berdimensi periode.
     * @param  'pembuatan'|'penyimpanan'  $tindakan
     */
    public function alasanTolak(User $aktor, PermissionDecision $keputusan, IndikatorKinerja $indikator, JadwalTahunan $jadwal, ?array $periodeIds, string $tindakan): ?string
    {
        $hariIni = today(config('app.business_timezone'))->toDateString();
        $penutupan = $jadwal->penutupan?->toDateString();
        if (is_string($penutupan) && $hariIni > $penutupan && ! $this->dalamKoreksiSah($indikator, $jadwal, $periodeIds)) {
            return "Tahun jadwal telah ditutup; {$tindakan} memerlukan sesi koreksi resmi.";
        }

        if ($this->jalurPerencanaan($keputusan)) {
            return null;
        }

        $pic = PenugasanIndikator::effectiveOn($hariIni)->where('indikator_id', $indikator->id)->first();
        if (! $pic instanceof PenugasanIndikator || (string) $pic->user_id !== (string) $aktor->id) {
            return 'Tindakan ini memerlukan penugasan PIC yang efektif.';
        }

        $mulai = $jadwal->rencana_aksi_mulai?->toDateString();
        $selesai = $jadwal->rencana_aksi_selesai?->toDateString();
        if (! is_string($mulai) || ! is_string($selesai) || $hariIni < $mulai || $hariIni > $selesai) {
            return 'Jendela penyusunan rencana aksi periode ini sudah ditutup.';
        }

        return null;
    }

    /**
     * Sesi koreksi sah bila jendela waktunya berjalan, `jenis_objek` memuat
     * `rencana_aksi`, dan indikator tercakup.
     */
    public function sesiKoreksiAktif(IndikatorKinerja $indikator, JadwalTahunan $jadwal): bool
    {
        if (! $jadwal->koreksi_mulai instanceof CarbonInterface || ! $jadwal->koreksi_sampai instanceof CarbonInterface) {
            return false;
        }
        if (! now()->betweenIncluded($jadwal->koreksi_mulai, $jadwal->koreksi_sampai)) {
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
        $periodeIds = ($jadwal->lingkup_koreksi ?? [])['periode_ids'] ?? [];

        return is_array($periodeIds) ? array_values(array_map(fn ($id): string => (string) $id, $periodeIds)) : [];
    }

    /**
     * Jalur global bila allow berasal dari peran, bukan hanya grant unit
     * (Data Model §2.23; ADR-0002). Allow yang hanya dari grant adalah jalur
     * PIC ber-scope unit yang tunduk pada PJ efektif dan jendela.
     */
    private function jalurPerencanaan(PermissionDecision $keputusan): bool
    {
        return $keputusan->allowed && ($keputusan->basis['sumber_allow']['roles'] ?? []) !== [];
    }

    /**
     * Setiap periode yang hendak ditulis wajib tercantum di lingkup koreksi;
     * acuannya periode yang diminta, bukan yang tersimpan, sehingga header
     * tanpa target lama pun tetap divalidasi.
     *
     * @param  list<string>|null  $periodeIds
     */
    private function dalamKoreksiSah(IndikatorKinerja $indikator, JadwalTahunan $jadwal, ?array $periodeIds): bool
    {
        if (! $this->sesiKoreksiAktif($indikator, $jadwal)) {
            return false;
        }

        return array_diff($periodeIds ?? [], $this->periodeLingkupKoreksi($jadwal)) === [];
    }
}
