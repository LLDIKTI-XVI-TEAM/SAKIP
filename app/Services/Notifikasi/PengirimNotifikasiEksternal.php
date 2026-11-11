<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Services\Pengingat\KelayakanPenerimaEws;
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
     * dinilai {@see KelayakanPenerimaEws}. Prefiks `jadwal_periode` tanpa gerbang fail-closed.
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
     * RA dan pengukuran tetap memakai Policy `view` yang sama dengan tautan. Untuk
     * `jadwal_periode`, kejadian berprefiks `ews:` mengambil H dari `parameter['h']`, sedangkan
     * `jendela_dibuka:` menghitung H dari tenggat jendela pada instan kirim; H hilang/invalid,
     * jendela yang sudah tertutup, prefiks lain, atau hasil kelayakan palsu berakhir `false`.
     * Untuk `ews:`, `parameter['jatuh_tempo']` yang berbeda dari tenggat terkini juga berakhir `false`.
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
