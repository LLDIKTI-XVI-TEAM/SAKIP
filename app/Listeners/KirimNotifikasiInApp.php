<?php

namespace App\Listeners;

use App\Events\JendelaPengisianDibuka;
use App\Events\PenggunaBaruTerdaftar;
use App\Events\PengukuranDiajukan;
use App\Events\PengukuranDibukaKembali;
use App\Events\PengukuranDikembalikan;
use App\Events\RencanaAksiDiajukan;
use App\Events\RencanaAksiDibukaKembali;
use App\Events\RencanaAksiDikembalikan;
use App\Models\PengukuranKinerja;
use App\Models\PeriodeJadwal;
use App\Models\RencanaAksi;
use App\Models\User;
use App\Notifications\ItemNotifikasi;
use App\Services\Notifikasi\PenerimaNotifikasi;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Menulis item notifikasi in-app setelah transaksi bisnis commit (event `ShouldDispatchAfterCommit`,
 * dijalankan sinkron tanpa queue). Listener hanya menyusun teks dan route; penerima dihitung
 * PenerimaNotifikasi. Galat apa pun dicatat tanpa isi dan tidak pernah mengubah hasil mutasi;
 * item yang gagal ditulis hilang tanpa retry. Ditemukan lewat event discovery (method `handle*`).
 * Item "jendela pengisian dibuka" dicegah ganda per jendela, tanggal bisnis pembukaan, dan penerima
 * (lihat `tulisJendelaDibuka`); jenis lain tetap ditulis lewat `notify()` dengan id acak.
 */
class KirimNotifikasiInApp
{
    public function __construct(private readonly PenerimaNotifikasi $penerima) {}

    public function handleRencanaAksiDikembalikan(RencanaAksiDikembalikan $event): void
    {
        $this->kirimPengembalianRencanaAksi('rencana_aksi.dikembalikan', 'dikembalikan', $event->rencanaAksiId, $event->penerimaId, $event->alasan);
    }

    public function handleRencanaAksiDibukaKembali(RencanaAksiDibukaKembali $event): void
    {
        $this->kirimPengembalianRencanaAksi('rencana_aksi.dibuka_kembali', 'dibuka kembali', $event->rencanaAksiId, $event->penerimaId, $event->alasan);
    }

    public function handlePengukuranDikembalikan(PengukuranDikembalikan $event): void
    {
        $this->kirimPengembalianPengukuran('pengukuran.dikembalikan', 'dikembalikan', $event->pengukuranId, $event->penerimaId, $event->alasan);
    }

    public function handlePengukuranDibukaKembali(PengukuranDibukaKembali $event): void
    {
        $this->kirimPengembalianPengukuran('pengukuran.dibuka_kembali', 'dibuka kembali', $event->pengukuranId, $event->penerimaId, $event->alasan);
    }

    /** Antrean review: Perencanaan peninjau unit RA, tanpa pengaju. */
    public function handleRencanaAksiDiajukan(RencanaAksiDiajukan $event): void
    {
        $this->kirim('rencana_aksi.diajukan', $event->rencanaAksiId, function () use ($event): array {
            // Versi terbaru + snapshotnya dimuat sekali agar gerbang `view` tiap kandidat tidak membacanya ulang.
            $header = RencanaAksi::with(['indikator', 'latestVersion.jadwalSnapshot'])->findOrFail($event->rencanaAksiId);

            return [$this->penerima->peninjauPerencanaan($header, 'rencana_aksi:verifikasi', (string) $header->unit_id, $event->aktorId),
                new ItemNotifikasi('rencana_aksi.diajukan', "Rencana Aksi {$header->indikator->kode} {$header->tahun} menunggu verifikasi",
                    $this->diajukanOleh($event->aktorId), 'rencana-aksi.review', ['rencanaAksi' => $event->rencanaAksiId])];
        });
    }

    /** Antrean verifikasi: Perencanaan peninjau unit target pengukuran, tanpa pengaju. */
    public function handlePengukuranDiajukan(PengukuranDiajukan $event): void
    {
        $this->kirim('pengukuran.diajukan', $event->pengukuranId, function () use ($event): array {
            $p = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot'])->findOrFail($event->pengukuranId);

            return [$this->penerima->peninjauPerencanaan($p, 'pengukuran:verifikasi', $p->targetUnitId(), $event->aktorId),
                new ItemNotifikasi('pengukuran.diajukan', "Pengukuran {$p->indikator->kode} {$p->periode->nama} {$p->tahun} menunggu verifikasi",
                    $this->diajukanOleh($event->aktorId), 'verifikasi.show', ['id' => $event->pengukuranId])];
        });
    }

    /** Satu item per penerima per periode yang terbuka; PJ dihitung pada tanggal bisnis aksi yang dibawa event. */
    public function handleJendelaPengisianDibuka(JendelaPengisianDibuka $event): void
    {
        foreach ($event->periodeJadwalIds as $periodeJadwalId) {
            $this->tulisJendelaDibuka($periodeJadwalId, $event->indikatorIds, $event->tanggalBisnis);
        }
    }

    /**
     * Penulis item "jendela pengisian dibuka" bersama untuk jalur aksi (listener ini) dan jalur tanggal (perintah
     * `notifikasi:lonceng-pembukaan`), sehingga penerima dan teksnya sama. Paling banyak satu item per jendela, tanggal
     * bisnis pembukaan, dan penerima dari jalur mana pun: menutup lalu membuka ulang pada hari yang sama tidak menambah
     * item, sedangkan pembukaan pada tanggal lain menulis item baru. Tidak pernah melempar.
     *
     * @param  list<string>|null  $indikatorIds  null = semua indikator; daftar = lingkup sesi koreksi
     * @param  string  $tanggalBisnis  tanggal bisnis WITA pembukaan, format `Y-m-d`
     */
    public function tulisJendelaDibuka(string $periodeJadwalId, ?array $indikatorIds, string $tanggalBisnis): void
    {
        $this->kirim('jadwal.jendela_dibuka', $periodeJadwalId, function () use ($periodeJadwalId, $indikatorIds, $tanggalBisnis): array {
            $jendela = PeriodeJadwal::with(['periode', 'jadwal'])->findOrFail($periodeJadwalId);
            $selesai = $jendela->pengisian_selesai->locale('id')->translatedFormat('j F Y');

            return [$this->penerima->pjJendela($periodeJadwalId, $indikatorIds, $tanggalBisnis),
                new ItemNotifikasi('jadwal.jendela_dibuka', "Jendela pengisian {$jendela->periode->nama} {$jendela->jadwal->tahun} dibuka", "Pengisian sampai {$selesai}", 'pengukuran.index')];
        }, kunciUnik: "{$periodeJadwalId}:{$tanggalBisnis}");
    }

    /** Pengelola akses diarahkan ke halaman peran yang sudah terfilter ke akun baru itu. */
    public function handlePenggunaBaruTerdaftar(PenggunaBaruTerdaftar $event): void
    {
        $this->kirim('pengguna.menunggu_peran', $event->userId, function () use ($event): array {
            $nama = User::findOrFail($event->userId)->nama;

            return [$this->penerima->pengelolaAkses(), new ItemNotifikasi('pengguna.menunggu_peran', 'Akun baru menunggu peran', $nama, 'role-assignment.index', ['user' => $event->userId])];
        });
    }

    private function diajukanOleh(string $aktorId): string
    {
        return 'Diajukan oleh '.User::findOrFail($aktorId)->nama;
    }

    /** Pengembalian dikirim ke PJ efektif saat pengembalian (termasuk bila PJ itu aktornya sendiri), kecuali yang ditolak eksplisit membaca unit objek. */
    private function kirimPengembalianRencanaAksi(string $jenis, string $kata, string $id, ?string $penerimaId, string $alasan): void
    {
        $this->kirim($jenis, $id, function () use ($jenis, $kata, $id, $penerimaId, $alasan): array {
            $header = RencanaAksi::with('indikator')->findOrFail($id);

            return [$this->penerima->pjPengembalian($penerimaId, 'rencana_aksi:read', (string) $header->unit_id),
                new ItemNotifikasi($jenis, "Rencana Aksi {$header->indikator->kode} {$header->tahun} {$kata}", $this->ringkas($alasan), 'rencana-aksi.show', ['rencanaAksi' => $id])];
        });
    }

    private function kirimPengembalianPengukuran(string $jenis, string $kata, string $id, ?string $penerimaId, string $alasan): void
    {
        $this->kirim($jenis, $id, function () use ($jenis, $kata, $id, $penerimaId, $alasan): array {
            $p = PengukuranKinerja::with(['indikator', 'periode', 'jadwalSnapshot'])->findOrFail($id);

            return [$this->penerima->pjPengembalian($penerimaId, 'pengukuran:read', $p->targetUnitId()),
                new ItemNotifikasi($jenis, "Pengukuran {$p->indikator->kode} {$p->periode->nama} {$p->tahun} {$kata}", $this->ringkas($alasan), 'pengukuran.edit', ['id' => $id])];
        });
    }

    /** Ringkasan alasan: spasi/baris baru diringkas, lebar maksimal 200 termasuk elipsis. Teks biasa, bukan HTML. */
    private function ringkas(string $alasan): string
    {
        return Str::limit(Str::squish($alasan), 199, '…');
    }

    /**
     * Pelindung terakhir: tidak ada Throwable yang boleh lolos dari listener, termasuk dari logger (channel `stack`
     * melempar bila file log tidak bisa ditulis). Listener berjalan setelah commit tetapi masih di dalam pemanggilan
     * `DB::transaction()` Action, sehingga galat yang lolos akan memicu kompensasi Action (mis. menghapus berkas unggahan)
     * padahal mutasinya sudah tersimpan. Cadangannya `error_log` tanpa pesan galat, alasan, atau judul.
     *
     * @param  Closure(): array{0: list<User>, 1: ItemNotifikasi}  $susun
     * @param  string|null  $kunciUnik  null = item biasa lewat `notify()`; terisi = item dicegah ganda per kunci dan penerima (`tulisSekali`)
     */
    private function kirim(string $jenis, string $objekId, Closure $susun, ?string $kunciUnik = null): void
    {
        try {
            $this->tulis($jenis, $objekId, $susun, $kunciUnik);
        } catch (Throwable $e) {
            error_log(sprintf('Notifikasi in-app gagal ditulis: jenis=%s objek_id=%s galat=%s', $jenis, $objekId, $e::class));
        }
    }

    /**
     * Susun penerima dan item lalu tulis per penerima dengan galat terisolasi: pemuatan objek/penerima yang gagal
     * menghentikan kejadian ini saja, dan insert yang gagal untuk satu penerima tidak menghentikan penerima lain.
     * Log hanya memuat jenis, id objek, id penerima, dan kelas galat; pesan galat bisa memuat alasan/binding.
     *
     * @param  Closure(): array{0: list<User>, 1: ItemNotifikasi}  $susun
     */
    private function tulis(string $jenis, string $objekId, Closure $susun, ?string $kunciUnik): void
    {
        try {
            [$penerima, $item] = $susun();
        } catch (Throwable $e) {
            Log::error('Notifikasi in-app gagal ditulis.', ['jenis' => $jenis, 'objek_id' => $objekId, 'galat' => $e::class]);

            return;
        }
        if ($penerima === []) {
            Log::info('Notifikasi in-app tanpa penerima.', ['jenis' => $jenis, 'objek_id' => $objekId]);
        }
        foreach ($penerima as $user) {
            try {
                if ($kunciUnik === null) {
                    $user->notify($item);
                } else {
                    $this->tulisSekali($user, $item, $kunciUnik);
                }
            } catch (Throwable $e) {
                Log::error('Notifikasi in-app gagal ditulis.', ['jenis' => $jenis, 'objek_id' => $objekId, 'penerima_id' => $user->id, 'galat' => $e::class]);
            }
        }
    }

    /**
     * Tulis item dengan id tetap (UUID v5 dari jenis, `$kunciUnik`, dan penerima) lewat `INSERT ... ON CONFLICT DO
     * NOTHING`, sehingga penulisan ulang kejadian yang sama diabaikan. Insert yang bentrok tidak boleh ditangkap sebagai
     * galat karena membatalkan transaksi PostgreSQL yang sedang berjalan; pelanggaran FK dan galat encode tetap melempar
     * sehingga terisolasi per penerima oleh pemanggil. Kolomnya sama dengan yang ditulis kanal `database`.
     */
    private function tulisSekali(User $user, ItemNotifikasi $item, string $kunciUnik): void
    {
        $sekarang = now();

        DB::table('notifications')->insertOrIgnore([
            'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, "sakip:notifications:{$item->jenis}:{$kunciUnik}:{$user->id}")->toString(),
            'type' => $item->databaseType($user),
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode($item->toDatabase($user), JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ]);
    }
}
