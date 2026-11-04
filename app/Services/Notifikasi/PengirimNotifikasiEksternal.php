<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class PengirimNotifikasiEksternal
{
    /** Klaim persisten ditutup hanya oleh hasil pasti; crash tetap ambigu.
     * @return int|null Jeda untuk antrean belum jatuh tempo atau penolakan sementara yang pasti belum diterima.
     */
    public function kirim(string $id, int $attempt, int $tries): ?int
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
                default => null,
            };
            $code = ! app(KonfigurasiNotifikasi::class)->aktif($p->kanal) ? 'kanal_nonaktif'
                : (! $user || $user->status !== 'aktif' || ! $model || ! Gate::forUser($user)->allows('view', $model) ? 'penerima_tidak_berhak'
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

        return DB::transaction(function () use ($row, $hasil, $attempt, $tries) {
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
                $upaya = max($attempt, $p->percobaan_siklus);
                $delay = max($hasil->jeda, [60, 300, 1800][min(max($upaya - 1, 0), 2)]);
                $p->update(['status' => $upaya >= $tries ? 'gagal' : 'antre', 'kode_hasil' => $upaya >= $tries ? 'percobaan_habis' : 'provider_sementara', 'coba_lagi_pada' => now()->addSeconds($delay)]);

                return $delay;
            }

            return null;
        });
    }
}
