<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Pengingat\KelayakanPenerimaEws;
use App\Services\Pengingat\PengingatKontekstual;
use App\Services\Pengingat\SusunRekapPerencanaan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PengirimNotifikasiEksternal
{
    /** Klaim persisten ditutup hanya oleh hasil pasti; crash tetap ambigu.
     *
     * Gerbang penerima dievaluasi ulang pada setiap upaya: RA/pengukuran memakai Policy `view`,
     * sedangkan `jadwal_periode` hanya menerima prefiks kejadian `ews:` dan `jendela_dibuka:`, keduanya
     * dinilai {@see KelayakanPenerimaEws}, serta `rekap:` yang dinilai {@see PenerimaNotifikasi::penerimaRekap()}.
     * Prefiks `jadwal_periode` tanpa gerbang fail-closed.
     *
     * @return int|null Jeda untuk antrean belum jatuh tempo atau penolakan sementara yang pasti belum diterima.
     */
    public function kirim(string $id, int $tries): ?int
    {
        $row = DB::transaction(function () use ($id) {
            $p = NotifikasiPengiriman::lockForUpdate()->findOrFail($id);
            if ($p->status !== 'antre') {
                return null;
            }
            if ($p->coba_lagi_pada?->isFuture()) {
                // Redelivery dapat mendahului release worker yang terputus.
                return max(1, $p->coba_lagi_pada->timestamp - now()->timestamp);
            }
            $user = User::find($p->penerima_user_id);
            $model = match ($p->objek_tipe) {
                'rencana_aksi' => RencanaAksi::find($p->objek_id),
                'pengukuran' => PengukuranKinerja::find($p->objek_id),
                'jadwal_periode' => PeriodeJadwal::find($p->objek_id),
                default => null,
            };
            $code = ! app(KonfigurasiNotifikasi::class)->aktif($p->kanal) ? 'kanal_nonaktif'
                : (! $user || $user->status !== 'aktif' || ! $model || ! $this->berhak($p, $user, $model) ? 'penerima_tidak_berhak'
                    : (! AntrekanNotifikasiEksternal::kontakValid($p->kanal, $p->alamat) ? 'kontak_tidak_tersedia' : null));
            if ($code) {
                $p->update(['status' => $code === 'kontak_tidak_tersedia' ? 'gagal' : 'dibatalkan', 'kode_hasil' => $code]);

                return null;
            }
            $p->update(['status' => 'perlu_rekonsiliasi', 'kode_hasil' => 'perlu_rekonsiliasi', 'percobaan' => $p->percobaan + 1, 'percobaan_siklus' => $p->percobaan_siklus + 1, 'dicoba_terakhir_pada' => now()]);

            if ($model instanceof PeriodeJadwal && str_starts_with($p->kejadian, 'jendela_dibuka:')) {
                // Gunakan tenggat dari gerbang kelayakan untuk provider. Snapshot sudah tersimpan saat antre.
                $p->parameter = [...$p->parameter, 'selesai' => $model->pengisian_selesai->locale('id')->translatedFormat('j F Y')];
            }

            return $p;
        });
        if (is_int($row)) {
            return $row;
        }
        if (! $row) {
            return null;
        }
        // Exception bebas tidak diteruskan ke reporter/failed_jobs maupun previous exception.
        try {
            $hasil = $row->driver === 'log' && $row->simulasi ? app(PenyediaKanalNotifikasi::class)->kirim($row) : HasilKirim::ambigu();
        } catch (Throwable) {
            $hasil = HasilKirim::ambigu();
        }

        return DB::transaction(function () use ($row, $hasil, $tries) {
            $p = NotifikasiPengiriman::lockForUpdate()->findOrFail($row->id);
            if ($p->status !== 'perlu_rekonsiliasi' || $p->percobaan !== $row->percobaan) {
                return null;
            }
            if ($hasil->jenis === 'diterima') {
                // Rujukan dibatasi karakter aman; adapter tidak mengembalikan body respons.
                $ref = $hasil->rujukan;
                $p->update(['status' => 'diterima_provider', 'kode_hasil' => null, 'rujukan_provider' => $ref !== null && preg_match('/^[a-zA-Z0-9:_-]{1,255}$/D', $ref) ? $ref : null, 'coba_lagi_pada' => null]);
            } elseif ($hasil->jenis === 'permanen') {
                $p->update(['status' => 'gagal', 'kode_hasil' => 'provider_ditolak']);
            } elseif ($hasil->jenis === 'sementara') {
                $upaya = $p->percobaan_siklus;
                $delay = max($hasil->jeda, [60, 300, 1800][min(max($upaya - 1, 0), 2)]);
                $p->update(['status' => $upaya >= $tries ? 'gagal' : 'antre', 'kode_hasil' => $upaya >= $tries ? 'percobaan_habis' : 'provider_sementara', 'coba_lagi_pada' => now()->addSeconds($delay)]);

                return $delay;
            }

            return null;
        });
    }

    /**
     * Gerbang penerima per jenis tujuan pada instan kirim.
     *
     * EWS dan pembukaan jendela mensyaratkan hak baca Pengukuran dan minimal satu kewajiban
     * dengan hak isi pada unit yang tidak ditolak baca.
     *
     * RA dan pengukuran tetap memakai Policy `view` yang sama dengan tautan. Untuk
     * `jadwal_periode`, kejadian berprefiks `ews:` mengambil H dari `parameter['h']`, sedangkan
     * `jendela_dibuka:` menghitung H dari tenggat jendela pada instan kirim; H hilang/invalid,
     * jendela yang sudah tertutup, prefiks lain, atau hasil kelayakan palsu berakhir `false`.
     * Untuk `ews:`, `parameter['jatuh_tempo']` yang berbeda dari tenggat terkini juga berakhir `false`.
     * `rekap:` mensyaratkan tenggat dan H yang sama dengan saat antre, jendela yang masih berjalan,
     * penerima yang masih penerima rekap Perencanaan, dan `parameter['cakupan_unit']` yang sama dengan
     * daftar unit yang ditolak baca bagi penerima pada instan kirim.
     */
    private function berhak(NotifikasiPengiriman $pengiriman, User $user, Model $model): bool
    {
        if (! ($model instanceof PeriodeJadwal)) {
            return Gate::forUser($user)->allows('view', $model);
        }

        if (str_starts_with($pengiriman->kejadian, 'jendela_dibuka:')) {
            // Pembukaan memakai kelayakan EWS dengan H = sisa hari sampai tenggat; H negatif berarti jendela sudah tertutup.
            $saat = CarbonImmutable::now(config('app.business_timezone'));
            $sisa = (int) $saat->startOfDay()->diffInDays(CarbonImmutable::parse($model->pengisian_selesai->toDateString(), config('app.business_timezone')), false);

            return $sisa >= 0 && app(KelayakanPenerimaEws::class)->layak($user, $model, $sisa, $saat);
        }

        if (str_starts_with($pengiriman->kejadian, 'rekap:')) {
            // Angka rekap dibekukan saat antre, jadi hanya dikirim pada hari H yang sama dengan tenggat yang sama,
            // selama jendela masih berjalan, penerima masih penerima rekap, dan unit yang ditolak baca tidak berubah.
            $saat = CarbonImmutable::now(config('app.business_timezone'));
            $penerima = app(PenerimaNotifikasi::class);

            return ($pengiriman->parameter['jatuh_tempo'] ?? null) === $model->pengisian_selesai->toDateString()
                && ($pengiriman->parameter['h'] ?? null) === SusunRekapPerencanaan::sisaHari($model, $saat)
                && app(PengingatKontekstual::class)->periode($saat)->contains('id', $model->id)
                && $penerima->penerimaRekap([$user->id]) !== []
                && ($pengiriman->parameter['cakupan_unit'] ?? null) === $penerima->cakupanRekap($user);
        }

        if (! str_starts_with($pengiriman->kejadian, 'ews:')) {
            return false;
        }

        $h = $pengiriman->parameter['h'] ?? null;
        if (! is_int($h) || $h < 0) {
            return false;
        }

        // Tenggat yang sudah berubah sejak antre tidak dikirim walau H kebetulan sama.
        if (($pengiriman->parameter['jatuh_tempo'] ?? null) !== $model->pengisian_selesai->toDateString()) {
            return false;
        }

        return app(KelayakanPenerimaEws::class)->layak(
            $user,
            $model,
            $h,
            CarbonImmutable::now(config('app.business_timezone')),
        );
    }
}
