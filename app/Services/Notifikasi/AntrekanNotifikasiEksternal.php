<?php

namespace App\Services\Notifikasi;

use App\Models\NotifikasiPengiriman;
use App\Models\PengukuranKinerja;
use App\Models\RencanaAksi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AntrekanNotifikasiEksternal
{
    /** Penerima ditentukan producer tepercaya; tidak melakukan pencarian PJ kedua.
     * @param  list<string>  $penerima
     * @param  list<string>  $kanal
     * @param  array<string, string|int|float|bool|null>  $parameter
     * @return Collection<int, NotifikasiPengiriman>
     */
    public function antre(string $kejadian, Model $tujuan, string $periode, array $penerima, array $kanal, string $template, array $parameter): Collection
    {
        $tipe = match (true) {
            $tujuan instanceof RencanaAksi => 'rencana_aksi',
            $tujuan instanceof PengukuranKinerja => 'pengukuran',
            default => throw new InvalidArgumentException('Jenis tujuan notifikasi belum didukung.'),
        };
        Validator::make(compact('kejadian', 'periode', 'kanal', 'template'), [
            'kejadian' => 'required|string|max:255', 'periode' => 'required|string|max:255',
            'kanal' => 'required|array', 'kanal.*' => 'in:email,whatsapp', 'template' => 'required|string|max:10000',
        ])->validate();
        foreach ($parameter as $key => $value) {
            if (! is_string($key) || preg_match('/token|password|secret|authorization|credential|api.?key/i', $key) || (! is_scalar($value) && $value !== null)) {
                throw new InvalidArgumentException('Parameter notifikasi harus berupa nilai non-rahasia yang dipilih producer.');
            }
        }

        try {
            return DB::transaction(function () use ($kejadian, $tujuan, $periode, $penerima, $kanal, $template, $parameter, $tipe) {
                $tujuan = $tipe === 'rencana_aksi' ? RencanaAksi::findOrFail($tujuan->getKey()) : PengukuranKinerja::findOrFail($tujuan->getKey());
                $rows = collect();
                foreach (array_unique($penerima) as $userId) {
                    $user = User::findOrFail($userId);
                    $userId = (string) $user->getKey();
                    foreach (array_unique($kanal) as $channel) {
                        $driver = config('notifikasi.kanal.'.$channel.'.driver', 'log');
                        if ($driver !== 'log') {
                            throw new InvalidArgumentException('Driver notifikasi belum didukung.');
                        }
                        $key = hash('sha256', json_encode([$kejadian, $tipe, (string) $tujuan->getKey(), $periode, $userId, $channel], JSON_THROW_ON_ERROR));
                        $adresse = $channel === 'email' ? $user->email : $user->nomor_telepon;
                        $code = ! app(KonfigurasiNotifikasi::class)->aktif($channel) ? 'kanal_nonaktif'
                            : (! self::kontakValid($channel, $adresse) ? 'kontak_tidak_tersedia' : null);
                        $row = new NotifikasiPengiriman([
                            'id' => (string) Str::uuid(), 'kunci_idempoten' => $key, 'kejadian' => $kejadian,
                            'objek_tipe' => $tipe, 'objek_id' => $tujuan->getKey(), 'konteks_periode' => $periode,
                            'kanal' => $channel, 'penerima_user_id' => $userId, 'alamat' => $adresse,
                            'template' => $template, 'parameter' => $parameter, 'driver' => $driver,
                            'simulasi' => true, 'status' => $code === null ? 'antre' : ($code === 'kanal_nonaktif' ? 'dibatalkan' : 'gagal'),
                            'kode_hasil' => $code, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $inserted = DB::table('notifikasi_pengiriman')->insertOrIgnore($row->getAttributes());
                        $row = NotifikasiPengiriman::where('kunci_idempoten', $key)->firstOrFail();
                        if ($inserted && $row->status === 'antre') {
                            app(AntreanNotifikasi::class)->dispatch($row->id);
                        }
                        $rows->push($row);
                    }
                }

                return $rows;
            });
        } catch (QueryException) {
            // SQL dapat membawa snapshot kontak/pesan; jangan teruskan ke reporter.
            throw new RuntimeException('notifikasi.gangguan_penyimpanan');
        }
    }

    /** Validasi dasar; normalisasi provider nyata ditetapkan saat integrasi provider. */
    public static function kontakValid(string $kanal, ?string $alamat): bool
    {
        return $alamat !== null && strlen($alamat) <= 255 && ($kanal === 'email'
            ? filter_var($alamat, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^\+?[0-9]{8,15}$/D', $alamat) === 1);
    }
}
